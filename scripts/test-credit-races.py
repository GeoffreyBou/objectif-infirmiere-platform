#!/usr/bin/env python3
"""Independent PHP/MySQL connections race against the same local wallet."""
import concurrent.futures,json,subprocess

def wp(*args):
 r=subprocess.run(['scripts/dc.sh','wp',*args],capture_output=True,text=True,check=True)
 return r.stdout.strip()
fixture=json.loads(wp('eval-file','/oi-scripts/credit-race-fixture.php'));u=fixture['user'];ids=fixture['ids']
def race(kind,targets):
 def worker(i):return json.loads(wp('eval-file','/oi-scripts/credit-race-worker.php',str(u),str(i),kind))
 with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:return list(pool.map(worker,targets))
try:
 results=race('FICHE',[ids[0]]*6)
 assert sum(r.get('consumed') is True for r in results)==1,results
 assert all(r.get('unlocked') for r in results),results
 print('Six déblocages simultanés de la même fiche : un seul débit.')
 results=race('QCM',ids)
 assert sum(r.get('consumed') is True for r in results)==5,results
 assert sum(r.get('code')=='oi_no_credit' for r in results)==1,results
 print('Six séries simultanées avec cinq crédits : cinq droits, un refus, aucun découvert.')
 results=race('IA',[0]*6)
 assert sum(r.get('state')=='reserved' for r in results)==1,results
 assert sum(r.get('code')=='oi_busy' for r in results)==5,results
 print('Six demandes IA simultanées : une seule réservation et cinq refus avant appel externe.')
 balances=json.loads(wp('eval',f'echo wp_json_encode(OI_Credits::balances({u}));'))
 assert balances=={'FICHE':4,'QCM':0,'IA':4},balances
 print('Soldes finaux exacts après concurrence réelle.')
finally:
 code=f'''global $wpdb; $u={u}; if(!str_starts_with(get_userdata($u)->user_login,'race_'))throw new RuntimeException('Compte inattendu'); foreach(['oi_credit_grants','oi_credit_operations','oi_wallets','oi_unlocks'] as $t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$u]); require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($u);'''
 wp('eval',code)
