#!/usr/bin/env python3
"""Prepare private, selective batches from authenticated WordPress WXR exports.
No users, payments, credentials, AI mappings, trashed fiches or legacy free pack.
"""
import argparse, hashlib, json, os, xml.etree.ElementTree as ET
from pathlib import Path
NS={'wp':'http://wordpress.org/export/1.2/','content':'http://purl.org/rss/1.0/modules/content/'}
META={'oi_docx_images','oi_source_key','oi_drive_file_id','oi_drive_modified','oi_source_hash','oi_source_code','oi_word_title','oi_title_custom','oi_quiz','oi_qcm_source','oi_art_code','oi_art_data','oi_art_source','oi_art_hash'}
def main():
 p=argparse.ArgumentParser();p.add_argument('exports',type=Path);p.add_argument('output',type=Path);p.add_argument('--premium-id',type=int,required=True);a=p.parse_args()
 a.output.mkdir(mode=0o700,parents=True,exist_ok=True);os.chmod(a.output,0o700)
 manifest={'source':'https://app-dev.objectif-infirmiere.fr','premium_id':a.premium_id,'records':[],'counts':{}}
 for kind,status in [('oi_fiche','publish'),('oi_fiche_art','private'),('oi_pack','publish')]:
  count=0
  for _,item in ET.iterparse(a.exports/(kind+'.xml'),events=('end',)):
   if item.tag!='item':continue
   source_id=int(item.findtext('wp:post_id',namespaces=NS));actual=item.findtext('wp:status',namespaces=NS)
   if actual!=status or (kind=='oi_pack' and source_id!=a.premium_id):item.clear();continue
   assert item.findtext('wp:post_type',namespaces=NS)==kind
   row={'source_id':source_id,'type':kind,'title':item.findtext('title') or '', 'content':item.findtext('content:encoded',namespaces=NS) or '', 'meta':{},'terms':[]}
   for meta in item.findall('wp:postmeta',NS):
    key=meta.findtext('wp:meta_key',namespaces=NS)
    if key in META:row['meta'][key]=meta.findtext('wp:meta_value',namespaces=NS) or ''
   for term in item.findall('category'):
    if term.get('domain','').startswith('oi_'):row['terms'].append({'taxonomy':term.get('domain'),'slug':term.get('nicename'),'name':term.text or ''})
   payload=json.dumps({'source':manifest['source'],'records':[row]},ensure_ascii=False,separators=(',',':')).encode()
   assert len(payload)<16000000
   filename=f'{kind}-{source_id}.json';dest=a.output/filename;dest.write_bytes(payload);dest.chmod(0o600)
   manifest['records'].append({'source_id':source_id,'type':kind,'filename':filename,'sha256':hashlib.sha256(payload).hexdigest(),'bytes':len(payload)})
   count+=1;item.clear()
  manifest['counts'][kind]=count
 assert manifest['counts']['oi_pack']==1
 dest=a.output/'manifest.json';dest.write_text(json.dumps(manifest,indent=2));dest.chmod(0o600)
 print(json.dumps({'counts':manifest['counts'],'total_bytes':sum(r['bytes'] for r in manifest['records'])}))
if __name__=='__main__':main()
