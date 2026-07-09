
import sys, zipfile, re, xml.etree.ElementTree as ET
sys.stdout.reconfigure(encoding='utf-8')

xlsx_path = r'c:/xampp/htdocs/fullstackpayroll/docs/payroll-requirements/KOMPONEN UPAH2.xlsx'
with zipfile.ZipFile(xlsx_path, 'r') as z:
    with z.open('xl/sharedStrings.xml') as f:
        ss_content = f.read().decode('utf-8')
    shared_strings = re.findall(r'<t[^>]*>([^<]*)</t>', ss_content)

    with z.open('xl/worksheets/sheet1.xml') as f:
        sheet_content = f.read().decode('utf-8')

    root = ET.fromstring(sheet_content)

    print('CELL VALUES FROM SHEET:')
    for row in root.iter('{http://schemas.openxmlformats.org/spreadsheetml/2006/main}row'):
        row_num = row.get('r')
        row_vals = []
        for cell in row:
            cell_type = cell.get('t')
            v = cell.find('{http://schemas.openxmlformats.org/spreadsheetml/2006/main}v')
            if v is not None and v.text:
                if cell_type == 's':
                    idx = int(v.text)
                    val = shared_strings[idx] if idx < len(shared_strings) else v.text
                else:
                    val = v.text
                row_vals.append(val.strip())
        if row_vals:
            sep = ' | '
            print('Row ' + str(row_num) + ': ' + sep.join(row_vals))
