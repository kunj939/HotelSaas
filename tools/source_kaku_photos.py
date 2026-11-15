"""Find reusable real-photo candidates. Human visual review is required before use.

python tools/source_kaku_photos.py CACHE_DIRECTORY
Searches Commons with attribution metadata, caches results, and respects rate limits.
"""
import argparse, html, json, re, time, urllib.parse, urllib.request
from concurrent.futures import ThreadPoolExecutor
from threading import Lock
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
UA='TableBloom-menu-photos/2.0 (dish-specific photo research)'
GATE=Lock()
LAST_REQUEST=0.0

def search(query):
    global LAST_REQUEST
    with GATE:
        time.sleep(max(0,6-(time.monotonic()-LAST_REQUEST)))
        LAST_REQUEST=time.monotonic()
    params=dict(action='query',generator='search',gsrsearch=query+' filetype:bitmap',
                gsrnamespace=6,gsrlimit=12,prop='imageinfo',iiprop='url|extmetadata|size',iiurlwidth=960,format='json')
    req=urllib.request.Request('https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode(params),headers={'User-Agent':UA})
    with urllib.request.urlopen(req,timeout=18) as response:data=json.load(response)
    results=[]
    for page in sorted(data.get('query',{}).get('pages',{}).values(),key=lambda p:p.get('index',0)):
        info=page['imageinfo'][0];meta=info.get('extmetadata',{})
        def clean(key):return html.unescape(re.sub('<[^>]+>','',meta.get(key,{}).get('value',''))).strip()
        license=clean('LicenseShortName');description=clean('ImageDescription')
        if not (license.startswith('CC BY') or license in ['CC0','Public domain']):continue
        if info['width']<600 or info['height']<400:continue
        if re.search(r'\b(ai.generated|midjourney|stable diffusion|dall.e|illustration|drawing)\b',page['title']+' '+description,re.I):continue
        results.append(dict(title=page['title'],artist=clean('Artist'),license=license,license_url=clean('LicenseUrl'),
                            source=info['descriptionurl'],original=info['url'],thumb=info.get('thumburl',info['url']),
                            width=info['width'],height=info['height'],description=description[:1500]))
    return results

def main():
    parser=argparse.ArgumentParser();parser.add_argument('cache',type=Path);args=parser.parse_args();args.cache.mkdir(parents=True,exist_ok=True)
    queries=json.loads((ROOT/'catalog/kaku_photo_queries.json').read_text(encoding='utf-8'))
    def task(pair):
        i,(name,query)=pair
        key=re.sub('[^a-z0-9]+','-',name.lower()).strip('-');path=args.cache/(key+'.json')
        if path.exists():return
        for attempt in range(3):
            try:
                result=search(query);path.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
                print(f'{i+1}/{len(queries)} {name}: {len(result)} candidates',flush=True);break
            except Exception as error:
                print(f'{name}: {error}',flush=True)
                time.sleep(20*(attempt+1))
    with ThreadPoolExecutor(max_workers=3) as pool:list(pool.map(task,enumerate(queries.items())))

if __name__=='__main__':main()
