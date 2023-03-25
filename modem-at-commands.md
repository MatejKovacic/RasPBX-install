# Sending AT commands to GSM USB modem
Chan dongle software enables users to send so called AT commands to your GSM stick. AT stands for *Attention* and these commands are used for controlling GSM modems.

You can send AT commands to any GSM modem (GSM USB stick) with commands:
```
sudo su
asterisk -rvvv
dongle cmd <device> <AT_command>
```

For instance, sending command to device `dongle0`:
```
sudo su
asterisk -rvvv
dongle cmd dongle0 ATI
```
This AT command will get relevant information from modem.

Some useful AT commands you can use:
```
AT command	Description
AT+CCWA=0,0,1                 disable call-waiting
AT+CFUN=1,1                   reboot modem
AT^CARDLOCK="<code>"	      send unlock code
AT^SYSCFG=13,0,3FFFFFFF,0,3   modem 2G only, automatic search any band, no roaming
AT^SYSCFG=2,0,3FFFFFFF,2,4    Any
AT^SYSCFG=13,1,3FFFFFFF,2,4   2G only
AT^SYSCFG=14,2,3FFFFFFF,2,4   3G only
AT^SYSCFG=2,1,3FFFFFFF,2,4    2G preferred
AT^SYSCFG=2,2,3FFFFFFF,2,4    3G preferred
AT^U2DIAG=0                   enable modem function only
ATI                           get relevant information from modem
ATZ                           reset modem configuration
AT+CIMI                       read IMSI
AT+CLCK="SC",0,"<pin>"        disable PIN verification
```

## Using 2G network only

In some cases USB dongles are not working properly. You can find out that they are using connectivity. If you will issue a command `dongle show device state dongle0` in that case, `GSM Registration Status` will be `Not registered` or something similar.

The problem is, that GSM modem is sometimes "flipping" between 2G and 3G network. Solution is, that you instruct the modem to stay on 2G network only. You can do this by typing these commands:
```
sudo su
asterisk -rvvv
dongle cmd dongle0 AT^SYSCFG=13,1,3FFFFFFF,2,4
```

But because you want this to be set automatically when the system boots up, just add this line into your root crontab (`sudo crontab -e`):
```
@reboot /usr/sbin/asterisk -rx 'dongle cmd dongle0 AT^SYSCFG=13,1,3FFFFFFF,2,4'
```

After reboot, your USB GSM modem will be automatically instructed to use 2G network only.
