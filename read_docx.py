import zipfile
import xml.etree.ElementTree as ET
import sys

sys.stdout.reconfigure(encoding='utf-8')

def extract_text_from_docx(docx_path):
    try:
        with zipfile.ZipFile(docx_path) as docx:
            xml_content = docx.read('word/document.xml')
            tree = ET.fromstring(xml_content)
            
            ns = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
            texts = tree.findall('.//w:t', ns)
            
            return '\n'.join([t.text for t in texts if t.text])
    except Exception as e:
        return str(e)

print(extract_text_from_docx('D:\\live_server\\Akshay_Adhav_Appraisal_FY2526.docx'))
