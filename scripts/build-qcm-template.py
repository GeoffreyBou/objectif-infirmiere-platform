"""Generate the data-only XLSX template using the Python standard library."""
from pathlib import Path
from xml.sax.saxutils import escape
from zipfile import ZipFile, ZIP_DEFLATED
headers=['code_question','enonce','A','B','C','D','E','bonnes_reponses','explication','statut']
rows=[headers,['B1-UGR-002-Q001','EXEMPLE DE FORMAT — Remplacer cette question et son corrigé.','Proposition A','Proposition B','Proposition C','','','A;C','Expliquer ici pourquoi A et C sont exactes et pourquoi les autres propositions ne le sont pas.','actif']]
help_rows=[['MODE D’EMPLOI'],['Un classeur par fiche. Remplacer la ligne exemple avant publication.'],['Nom : B1-UGR-002_Sante-sexuelle_qcm.xlsx. Conserver le code au début du nom.'],['Conserver les noms des colonnes et l’onglet Questions. 200 questions maximum.'],['Un code permanent par question : B1-UGR-002-Q001, Q002, etc. Ne pas réutiliser un code pour une autre question.'],['Deux à cinq propositions dans ce modèle. Possibilité d’ajouter les colonnes F, G et H.'],['Bonnes réponses : lettres séparées par des points-virgules, par exemple A ou A;C.'],['Explication obligatoire. Une seule bonne réponse = QCU, plusieurs = QCM.'],['Statut actif ou archive. Une ligne absente du fichier ne supprime aucune question déjà publiée.'],['Pas de formules, macros ou liens vers des classeurs externes. Coller des valeurs uniquement.'],['Déposer le classeur à côté du Word dans Drive, puis Gérer les QCM → Analyser → Préparer → Prévisualiser → Publier.']]
def sheet(rows):
 out=[]
 for r,row in enumerate(rows,1):
  cells=''.join(f'<c r="{chr(65+c)}{r}" t="inlineStr"><is><t xml:space="preserve">{escape(str(v))}</t></is></c>' for c,v in enumerate(row))
  out.append(f'<row r="{r}">{cells}</row>')
 return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="10" width="30" customWidth="1"/></cols><sheetData>'+''.join(out)+'</sheetData></worksheet>'
path=Path(__file__).resolve().parents[1]/'plugin/objectif-infirmiere/assets/modele-qcm.xlsx'
with ZipFile(path,'w',ZIP_DEFLATED) as z:
 z.writestr('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>')
 z.writestr('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>')
 z.writestr('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Questions" sheetId="1" r:id="rId1"/><sheet name="Mode d’emploi" sheetId="2" r:id="rId2"/></sheets></workbook>')
 z.writestr('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>')
 z.writestr('xl/worksheets/sheet1.xml',sheet(rows));z.writestr('xl/worksheets/sheet2.xml',sheet(help_rows))
print(path)
