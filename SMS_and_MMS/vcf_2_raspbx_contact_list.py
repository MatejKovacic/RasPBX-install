import sys
import quopri

def decode_quoted_printable(text):
    """Decode quoted-printable text"""
    return quopri.decodestring(text.encode()).decode('utf-8')

def extract_contact_numbers(vcf_file):
    """Extract contact names and all phone numbers"""
    contacts = []
    current_name = ""
    current_numbers = []
    
    with open(vcf_file, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            
            if line == "BEGIN:VCARD":
                current_name = ""
                current_numbers = []
            elif line == "END:VCARD":
                if current_name:
                    contacts.append((current_name, current_numbers))
            elif line.startswith("FN:"):
                current_name = line[3:]
                if 'QUOTED-PRINTABLE' in line.upper():
                    current_name = decode_quoted_printable(current_name)
            elif line.startswith(("TEL;", "TEL:")):
                phone = line.split(':')[-1]
                if 'QUOTED-PRINTABLE' in line.upper():
                    phone = decode_quoted_printable(phone)
                current_numbers.append(phone)
    
    return contacts

def print_tab_delimited(contacts):
    """Print in tab-delimited format with one number per line"""
    print("Name\tPhone Number")  # Header
    for name, numbers in contacts:
        if numbers:
            for number in numbers:
                print(f"{name}\t{number}")
        else:
            print(f"{name}\t")  # For contacts with no numbers

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(f"Usage: {sys.argv[0]} <vcf_file>")
        sys.exit(1)
    
    vcf_file = sys.argv[1]
    contacts = extract_contact_numbers(vcf_file)
    print_tab_delimited(contacts)
