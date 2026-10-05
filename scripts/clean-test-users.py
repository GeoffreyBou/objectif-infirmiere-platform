#!/usr/bin/env python3
"""Remove only verified local browser fixtures; never delete by an unverified ID."""
import json, re, subprocess, sys
from pathlib import Path
from urllib.parse import urlsplit

def wp(*args):
    return subprocess.run(['scripts/dc.sh','wp',*args],capture_output=True,text=True)

mode=sys.argv[1]
path=Path('.runtime/browser-user.json' if mode=='browser' else '.runtime/registration-test-user.json')
if not path.exists():
    raise SystemExit(0)
home=wp('option','get','home')
if home.returncode or urlsplit(home.stdout.strip()).hostname not in ['localhost','127.0.0.1']:
    raise SystemExit('Nettoyage réservé à WordPress local.')
fixture=json.loads(path.read_text())
entries=[fixture]+list(fixture.get('projects',{}).values()) if mode=='browser' else [fixture]
seen=set()
for entry in entries:
    identity=str(entry['id']) if mode=='browser' else entry.get('email','')
    if identity in seen: continue
    seen.add(identity)
    if mode!='browser' and not re.fullmatch(r'oi_e2e_signup_\d+@example\.invalid',identity):
        raise SystemExit('Identité de test non reconnue.')
    result=wp('user','get',identity,'--fields=ID,user_login,user_email,roles','--format=json')
    if result.returncode:
        # Confirm absence, rather than treating arbitrary command failures as missing users.
        listing=wp('user','list','--fields=ID,user_login,user_email','--format=json')
        if listing.returncode:
            raise SystemExit('Impossible de vérifier les comptes de test.')
        if any(str(u['ID'])==identity or u['user_email']==identity for u in json.loads(listing.stdout)):
            raise SystemExit('Compte présent mais lecture impossible : nettoyage interrompu.')
    else:
        account=json.loads(result.stdout)
        roles=account['roles'] if isinstance(account['roles'],list) else [r.strip() for r in account['roles'].split(',')]
        if roles!=['oi_etudiant']:
            raise SystemExit('Rôle inattendu : aucun compte supprimé.')
        if mode=='browser' and (account['user_login']!=entry.get('login') or not account['user_login'].startswith('browser_')):
            raise SystemExit('La fixture ne correspond pas au compte local : aucune suppression.')
        deleted=wp('user','delete',str(account['ID']),'--yes')
        if deleted.returncode:
            raise SystemExit('Suppression de la fixture impossible.')
path.unlink()
print('Compte de test local nettoyé.')
