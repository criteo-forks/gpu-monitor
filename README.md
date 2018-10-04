# GPU Monitor

This is a tool intended to monitor the GPU usage on the various GPU-servers at the LIP6 Lab, UPMC, Paris. This code has been written with the "quickest and dirtiest" principle in mind, it is absolutely awful, please do not read it :persevere:

The principle is as follows. A bunch of Bash / Python scripts runs regularly `nvidia-smi` and `ps` to extract data and sends them to my `public_html` space. Each time someone wants to see the status of the GPUs, the page `index.php` reads the latest data files for each server and displays those.

This is a fork of https://github.com/ThomasRobertFr/gpu-monitor, with following changes:
* UI reworked
* Bug fixes
* Install scripts 
* Consul discovery of GPU hosts

## How to setup

### Monitoring setup

Put the files that are in the `scripts` folder on the machines you want to monitor. The scripts are as follows:

* `gpu-run.sh <task_id>` loops on one of the three tasks (`task_id` being `1`, `2` or `3`). Task 1 extracts GPU usage stats each 20s, task 2 extracts GPU processes each 20s, task 3 extracts ps info tha corresponds to GPU processes each 10s and copies all the monitoring files to the `public_html` space. This scripts uses the `HOST` env variable.
* `gpu-processes.py` is what's ran by task 3
* `gpu-check.sh <hostname>` checks if the 3 tasks are running, if not it will launch them in the background. Also `gpu-check.sh kill` will stop the tasks if running.

Just edit `gpu-run.sh` to change the `scp` command that is in it so that it sends file to the right location (i.e. the `data` folder of the _www_ location of the web monitor). If you do need scp, make sure you have an SSH keys setup so that we can do passwordless copy.

Ideally, on the machines you want to monitor, use the following cron jobs:

```
# Edit full-caps infos below
# Check if monitoring running each 5 min
*/5 * * * * /SCRIPT-LOCATION/gpu-check.sh HOSTNAME > /dev/null 2>&1
# Kill and restart the monitoring each 2 hours to cleanup the ouptput files of the monitors
0 */2 * * * /SCRIPT-LOCATION/gpu-check.sh kill > /dev/null 2>&1; /SCRIPT-LOCATION/gpu-check.sh HOSTNAME > /dev/null 2>&1
```

### Web interface setup

To setup the web interface, you just need to put the files of the repo (except `scripts` folder) on the www space of a web server that supports PHP.

Simply edit the `index.php` file to each the `$HOSTS` variable and optionnaly the `$SHORT_GPU_NAMES` variable.

`$HOSTS` associates the hostnames with some viewable names for these hosts. The keys are the ones entered as `HOSTNAME` in the crontab above and the `<hostname>` parameter of `gpu-check`.

`$SHORT_GPU_NAMES` allows you to rewrite GPU names if you want. It associates the names given by `nvidia-smi` to the names you want to be displayed.

## How to add reservations

To add reservations, it is possible to add booking information machine-by-machine though UI or to use `data/comments.json` with the following format:

```json
{
  "GPU_MACHINE_NAME": [  // you could also use a dictionary instead of a list { "0": {"name": ....}, "1": {"name": ...} }
    {  // an element with index 0, it corresponding to GPU0 of this machine
      "name": "WHO_IS_USING_IT",
      "date": "DATETIME_UNTIL_IT_IS_BOOKED",  // this machine will be indicated booked unless this datetime is in the past
      "comment": "FREE_TEXT_COMMENT"
    },
    { // an element with index 1, it corresponding to GPU1 of this machine
      "name": "SOMEONE_ELSE",
      "date": "SOME_WHERE_IN_A_PAST",  // this machine is not booked, datetime is in the past
      "comment": "booked by P�re No�l"
    }
  ],
  "gputest001-pa4": [  // GPU0 and GPU1 are reserved
    {
      "name": "sclaus",
      "date": "2018-07-01 00:00",
      "comment": "booked by Santa Claus"
    },
    {
      "name": "pnoel",
      "date": "2018-08-01 00:00",
      "comment": "booked by P�re No�l"
    }
  ],
  "gputest002-pa4": []  // no reservation info for this machine
}
```