## How to install Wireguard for RasPBX network

### Prerequisites

- Wireguard does not have a *server - client* infrastructure, but all endpoints are *peers*.
- We will call Wireguard "server" a Wireguard peer, with fixed, public IP, which will act as an internat gateway machine for other Wireguard peers.
- We will call Wireguard "client" a peer (mobile phone, computer,...), which will connect to the Wireguard "server".
- VPN IP address of the "server" will be `10.10.6.1`.
- VPN IP address of the first "client" will be `10.10.6.2`.
- Wireguard "server" is in our case installed on a Debian 11 server with public IP.
- VPN connections from peers ("clients") are done through 51194 port on UDP protocol.

### Install Wireguard and create "server" keys
```
sudo apt install wireguard
sudo -i
cd /etc/wireguard/
```

Create keys for "server":
```
umask 077; wg genkey | tee privatekey | wg pubkey > publickey
```

List the keys: `ls -l privatekey publickey`.

Print and save the keys for later use. `cat privatekey`:
**server_private_key**

`cat publickey`:
r06dE3avC3FISVREu4qT8Y2WcFo3+uVHIdsAsYhCBxc= (**server_public_key**)

A little security precaution: `sudo chmod 600 /etc/wireguard/{privatekey,wg0.conf}`

### Enable firewall rule (if UFW is used)

Allow 51195 port on UDP: `sudo ufw allow 51194/udp`

### Create iptables rules for traffic forwarding

We will create two scripts, one for establishibg *iptables* rules, and the ather to remove them (when WG connection is not active).

`sudo nano /etc/wireguard/add-nat-routing.sh`:

```
#!/bin/bash
IPT="/sbin/iptables"
IPT6="/sbin/ip6tables"

IN_FACE="ens3"                   # NIC connected to the internet
WG_FACE="wg0"                    # WG NIC
SUB_NET="10.10.6.0/24"           # WG IPv4 sub/net aka CIDR
WG_PORT="51194"                  # WG udp port
SUB_NET_6="fd42:42:42:42::/112"  # WG IPv6 sub/net

## IPv4 ##
$IPT -t nat -I POSTROUTING 1 -s $SUB_NET -o $IN_FACE -j MASQUERADE
$IPT -I INPUT 1 -i $WG_FACE -j ACCEPT
$IPT -I FORWARD 1 -i $IN_FACE -o $WG_FACE -j ACCEPT
$IPT -I FORWARD 1 -i $WG_FACE -o $IN_FACE -j ACCEPT
$IPT -I INPUT 1 -i $IN_FACE -p udp --dport $WG_PORT -j ACCEPT

# Peers can see each other
$IPT -I FORWARD -i $WG_FACE -o $WG_FACE -j ACCEPT

## IPv6 (Uncomment) ##
## $IPT6 -t nat -I POSTROUTING 1 -s $SUB_NET_6 -o $IN_FACE -j MASQUERADE
## $IPT6 -I INPUT 1 -i $WG_FACE -j ACCEPT
## $IPT6 -I FORWARD 1 -i $IN_FACE -o $WG_FACE -j ACCEPT
## $IPT6 -I FORWARD 1 -i $WG_FACE -o $IN_FACE -j ACCEPT
```

Make it executable: `sudo chmod +x /etc/wireguard/add-nat-routing.sh`

`sudo nano /etc/wireguard/remove-nat-routing.sh`:

```
#!/bin/bash
IPT="/sbin/iptables"
IPT6="/sbin/ip6tables"

IN_FACE="ens3"                   # NIC connected to the internet
WG_FACE="wg0"                    # WG NIC
SUB_NET="10.10.6.0/24"            # WG IPv4 sub/net aka CIDR
WG_PORT="51194"                  # WG udp port
SUB_NET_6="fd42:42:42:42::/112"  # WG IPv6 sub/net

# IPv4 rules #
$IPT -t nat -D POSTROUTING -s $SUB_NET -o $IN_FACE -j MASQUERADE
$IPT -D INPUT -i $WG_FACE -j ACCEPT
$IPT -D FORWARD -i $IN_FACE -o $WG_FACE -j ACCEPT
$IPT -D FORWARD -i $WG_FACE -o $IN_FACE -j ACCEPT
$IPT -D INPUT -i $IN_FACE -p udp --dport $WG_PORT -j ACCEPT

# Peers can see each other
$IPT -D FORWARD -i $WG_FACE -o $WG_FACE -j ACCEPT

# IPv6 rules (uncomment) #
## $IPT6 -t nat -D POSTROUTING -s $SUB_NET_6 -o $IN_FACE -j MASQUERADE
## $IPT6 -D INPUT -i $WG_FACE -j ACCEPT
## $IPT6 -D FORWARD -i $IN_FACE -o $WG_FACE -j ACCEPT
## $IPT6 -D FORWARD -i $WG_FACE -o $IN_FACE -j ACCEPT
```

Make it executable: `sudo chmod +x /etc/wireguard/remove-nat-routing.sh`

### Enable IPv4 forwarding

`nano /etc/sysctl.d/10-wireguard.conf`:

```
net.ipv4.ip_forward=1
net.ipv6.conf.all.forwarding=1
```

Reload all changes: `sysctl -p /etc/sysctl.d/10-wireguard.conf`.

### Create "client" keys and config

`sudo mkdir -p /etc/wireguard/clients`

We will create client keys and config for user *Matej*: `wg genkey | sudo tee /etc/wireguard/clients/Matej.key | wg pubkey | sudo tee /etc/wireguard/clients/Matej.key.pub`

Create PSK keys: `wg genpsk > /etc/wireguard/clients/Matej.psk`

`cat /etc/wireguard/clients/Matej.key`
(**client_Matej_private_key**)

`cat /etc/wireguard/clients/Matej.key.pub`:
`tV4rTz42hbs+9hxCjZsQjHqfp9k8ex6uPi4x2PDCX3I=` (**client_Matej_public_key**)

`cat /etc/wireguard/clients/Matej.psk`:
(**client_Matej_PSK**)

Now we create a config: `sudo nano /etc/wireguard/clients/Matej.conf`:

```
[Interface]
PrivateKey = **client_Matej_private_key**
Address = 10.10.6.2/32

[Peer]
PublicKey = r06dE3avC3FISVREu4qT8Y2WcFo3+uVHIdsAsYhCBxc= (**server_public_key**)
PresharedKey = **client_Matej_PSK**
Endpoint = xxx.xxx.xxx.xxx:51194
AllowedIPs = 0.0.0.0/0
PersistentKeepalive = 15
```

Please note:
- `AllowedIPs` means that we allow remote server as internet gateway.
- **If you do not want that Wireguard "server" will be used as default gateway to the internet**, set `AllowedIPs` to `AllowedIPs = 10.10.6.0/24`!
- `PersistentKeepalive` means that tunnel will be active, so we will be able to ping "client" or make connection to client through VPN.
- `Endpoint` is IP address of a Wireguard "server. 

### "Server" config

Finally, the "server": `sudo nano /etc/wireguard/wg0.conf`:

```
[Interface]
Address = 10.10.6.1/24
#SaveConfig = true (we don't want this)
PostUp = /etc/wireguard/add-nat-routing.sh
PostDown = /etc/wireguard/remove-nat-routing.sh
ListenPort = 51194
PrivateKey = **server_private_key**

[Peer]
# Matej
PublicKey = tV4rTz42hbs+9hxCjZsQjHqfp9k8ex6uPi4x2PDCX3I= (**client_Matej_public_key**)
PresharedKey = **client_Matej_PSK**
AllowedIPs = 10.10.6.2/32
```

Please note:
- "clients" (a.k.a peers) are going to `[Peer]` section
- client's IP should end with  `/32`: `AllowedIPs = 10.10.10.2/32`

### Generate "client" QR code

Install qrencode: `sudo apt install qrencode`.

Print the QR code on console. We will scan it with mobile phone later:

```
sudo su
qrencode -t ansiutf8 < /etc/wireguard/clients/Matej.conf
```

### Activate Wireguard "server"

Test internet connectivity of the "server" without Wireguard active: `curl -I https://siol.net`. We should get:

```
HTTP/2 200 
server: nginx
```

Now we activate the Wireguard "server: `systemctl restart wg-quick@wg0.service`.


Check the wg interface - `sudo wg`:

```
interface: wg0
  public key: r06dE3avC3FISVREu4qT8Y2WcFo3+uVHIdsAsYhCBxc=
  private key: (hidden)
  listening port: 51194

peer: tV4rTz42hbs+9hxCjZsQjHqfp9k8ex6uPi4x2PDCX3I=
  allowed ips: 10..10.2/32
```

`sudo ip a show wg0`:

```
6: wg0: <POINTOPOINT,NOARP,UP,LOWER_UP> mtu 1420 qdisc noqueue state UNKNOWN group default qlen 1000
    link/none 
    inet 10..0.1/24 scope global wg0
       valid_lft forever preferred_lft forever
```

Now we test internet connectivity again (with Wireguard active): `curl -I https://siol.net`. We should see that everything is working:

```
HTTP/2 200 
server: nginx
```

### Test Wireguard "client" on a phone

- Install Wireguard application and import WG configuration with QR code scan.
- Activate WG connection on a phone.
- Try to open random website **on a phone** (should be working).
- Open https://api.ipify.org/ **on a phone** (should see Wireguard "server's" public IP). 
- Ping Wireguard "server" **on a phone** (ping `10.10.6.1`, should be working).
- On **a WG "server"** ping the phone (`ping 10.10.6.2`, should be working).

### Enable Wireguard on a server at startup

Enable it via systemctl: `sudo systemctl enable wg-quick@wg0`. Check with rebooting the server: `sudo reboot`.

### Wireguard "server" management

Start and stop Wireguard "server" via *systemctl*:
- `sudo systemctl start wg-quick@wg0`.
- `sudo systemctl status wg-quick@wg0`.

Start and stop Wireguard "server" *manually*:
- `wg-quick up /etc/wireguard/wg0.conf`.
- `wg-quick down /etc/wireguard/wg0.conf`.

### PSK

*The purpose of PSK (Wireguard's pre-shared key) is to be resistant to the potential threat of Quantum Computers. A (large enough) quantum computer would be able to break the public key crypto that is used for the handshake. If you add a pre-shared key into the mix, the derived encryption and authentication keys will also depend on this key preventing this kind of quantum computer attack.*

Using preshared keys means that even if an attacker calculated (or was able to steal) the private keys used by a WireGuard connection, he still wouldn’t be able to decrypt the connection’s encrypted WireGuard traffic. To do so, she would also have to steal the preshared key (a randomly-generated preshared key would not be "crackable" by quantum computers, nor by any other means). More [here](https://www.procustodibus.com/blog/2021/09/wireguard-key-rotation/).

Generate PSK: `wg genpsk > peer.psk`.

It PSK for each client should be added:
- in "server" config under `[Peer]`
- in "client" config under `[Peer]`
- these PSK's are the same, but each "client-server" pair has it's own PSK
- directive is: `PresharedKey = <pre-shared key for this peer>`

### Speed test

Speedtest **without Wireguard** on Android phone (connected to WiFi):
- download: 51,2
- upload: 19,4
- ping:
 - idle: 6
 - download: 109
 - upload: 38
- Jitter: 
 - idle: 6 (low: 5, high: 7)
 - download: 109 (low: 15, high: 469)
 - upload: 38 (low: 6, high: 505).
 
Speedtest **with Wireguard** on Android phone (connected to WiFi):
- download: 39,8
- upload: 17,7
- ping:
 - idle: 18
 - download: 173
 - upload: 91
- Jitter: 
 - idle: 3 (low: 16, high: 25)
 - download: 57 (low: 47, high: 551)
 - upload: 40 (low: 16, high: 622).

![](https://www.ckn.io/images/wireguard_comparisions.png)

### Other tests
- Calling between two Wireguard peers works.
- When calling to ordinary number (on *USB dongle*), ring group is activated and multiple devices (some on OpenVPN and others on Wireguard) ring simultaneously.
- When iPhone is in "sleep mode" (and does not respond to a ping from Wireguard server), Zoiper client does not ring when called (i. e. get rid of iPhone).
- When Android is in "sleep mode" Zoiper client rings (because Android is keeping the connection alive). This could be solved by [appropriate Android settings](https://dontkillmyapp.com/).
