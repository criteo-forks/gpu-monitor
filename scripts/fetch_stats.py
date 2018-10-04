#! /usr/bin/env python

import re
import time
import urllib
import urllib2
import json
import os

CONFIG_FILE=os.environ.get('FETCH_STATS_CONFIG', 'fetch_stats_conf.json')

def get_file_names(url):
    response = urllib2.urlopen(url)
    html = response.readlines()
    file_name_re = re.compile("<li><a href=\"([^\"]*)\">([^<]*)</a>")
    output = []
    for line in html:
        file_names = file_name_re.findall(line)
        if file_names:
            output.append(file_names[0][0])
    return output


def download_files(url, list_files):
    for file_name in list_files:
        if '_' in file_name:
            urllib.urlretrieve (url+'/'+file_name, file_name)

def get_config():
    if not os.path.isfile(CONFIG_FILE):
        return None
    with open(CONFIG_FILE) as f:
        return json.loads(f.read())

def get_consul_uri():
    cfg = get_config()
    if cfg:
        return cfg.get('CONSUL_SERVICE_URI',None)
    else:
        return None

def get_nodes():
    consul_uri = get_consul_uri()
    if not consul_uri:
        print('[WARNING] Could not find consul service URI')
        print('[WARNING] Please add a CONSUL_SERVICE_URI to ', CONFIG_FILE)
        return []
    response = urllib2.urlopen(consul_uri)
    print('CODE: ', response.getcode())
    if response.getcode() != 200:
        return []
    services = json.loads(response.read())
    return [x['Node'] for x in services if 'Node' in x]

def get_urls(nodes):
    return [ 'http://{}:8114'.format(node) for node in nodes ]

def dump_hosts(nodes):
    # used by the PHP page to know the list of ...hosts:
    # as { short_name : fqdn }
    with open('hosts.json', 'w+') as of:
        of.write(json.dumps({ x.split('.')[0] : x for x in nodes  }))

if __name__ == '__main__':
    while True:
        nodes = get_nodes()
        for url in get_urls(nodes):
            try:
                list_files = get_file_names(url)
                download_files(url, list_files)
            except:
                pass
        dump_hosts(nodes)
        time.sleep(20)
