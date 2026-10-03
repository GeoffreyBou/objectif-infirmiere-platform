#!/usr/bin/env python3
"""Read-only WordPress inventory. Credentials stay in the scoped network proxy."""
import base64
import json
import os
import urllib.error
import urllib.request

ORIGIN = 'https://app-dev.objectif-infirmiere.fr'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None  # Never forward credentials to a different URL.

def main():
    password = os.environ.get('OI_WP_APPLICATION_PASSWORD', '')
    if password:
        authorization = 'Basic ' + base64.b64encode(('dev-agent:' + password).encode()).decode()
    else:
        authorization = os.environ.get('OI_WP_AUTHORIZATION', '')
    if not authorization:
        print('Accès WordPress non configuré.')
        return 2
    opener = urllib.request.build_opener(NoRedirect)

    def read(path):
        request = urllib.request.Request(ORIGIN + path, headers={
            'Authorization': authorization, 'Accept': 'application/json',
            'Cache-Control': 'no-cache',
        })
        try:
            with opener.open(request, timeout=25) as response:
                raw = response.read(2097153)
                if len(raw) > 2097152:
                    return response.status, {'error': 'Réponse trop volumineuse'}, False
                data = json.loads(raw)
                return response.status, data, True
        except urllib.error.HTTPError as error:
            try:
                data = json.loads(error.read(8192))
            except (ValueError, UnicodeDecodeError):
                data = {}
            return error.code, {'code': data.get('code')}, False
        except (urllib.error.URLError, ValueError) as error:
            return None, {'error_type': type(error).__name__}, False

    for prefix in ['/wp-json/', '/index.php/wp-json/']:
        code, user, ok = read(prefix + 'wp/v2/users/me?context=edit&_fields=id,roles,capabilities')
        print(json.dumps({'section': 'authentication', 'route': prefix, 'http': code,
                          'code': user.get('code'), 'roles': user.get('roles')}, ensure_ascii=False))
        if not ok:
            continue
        if not user.get('capabilities', {}).get('manage_options'):
            print('Compte authentifié mais capacité manage_options absente.')
            return 2
        complete = True
        for label, route in [
            ('plugins', 'wp/v2/plugins?context=edit&_fields=plugin,name,version,status'),
            ('themes', 'wp/v2/themes?context=edit&_fields=stylesheet,template,name,version,status'),
            ('settings', 'wp/v2/settings?_fields=title,url,timezone'),
        ]:
            status, data, success = read(prefix + route)
            complete = complete and success
            print(json.dumps({'section': label, 'http': status, 'data': data}, ensure_ascii=False))
        return 0 if complete else 2
    return 2

if __name__ == '__main__':
    raise SystemExit(main())
