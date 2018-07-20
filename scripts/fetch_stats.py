import re
import time
import urllib
import urllib2


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


urls = ['http://gputest001-pa4.central.criteo.preprod:8114', 
        'http://gputest002-pa4.central.criteo.preprod:8114', 
        'http://gputest004-pa4.central.criteo.preprod:8114',
        'http://gputest001-pa4.central.criteo.prod:8114',
        'http://gputest002-pa4.central.criteo.prod:8114',
        'http://gputest003-pa4.central.criteo.prod:8114',
        'http://gputest004-pa4.central.criteo.prod:8114',
        'http://gputest005-pa4.central.criteo.prod:8114',
        'http://gputest006-pa4.central.criteo.prod:8114',
        'http://gputest007-pa4.central.criteo.prod:8114',
        'http://gputest008-pa4.central.criteo.prod:8114',
        'http://gputest009-pa4.central.criteo.prod:8114',
        'http://gputest010-pa4.central.criteo.prod:8114',
        'http://gputest011-pa4.central.criteo.prod:8114',
        'http://gputest012-pa4.central.criteo.prod:8114']
while True:
    try:
        while True:
            for url in urls:
                list_files = get_file_names(url)
                download_files(url, list_files)
            time.sleep(20)
    except:
        time.sleep(60)

