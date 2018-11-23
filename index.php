<?php if (!isset($_GET["content"])) { ?>

<?php
function parse_df_file($filename){
    $disks = array();
    foreach(file($filename) as $line){
            $tokens = preg_split("#\s+#", $line);
            if(stripos($tokens[0], '/dev/') === false) {
                    continue;
            }
            $disks[ $tokens[5] ] = array(
                    "total" => round($tokens[1]/1024/1024, -1),
                    "used" => round($tokens[2]/1024/1024, -1),
                    "usage" => trim($tokens[4],'%')
            );
    }
    return $disks;
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>GPU Status</title>

    <!--<meta http-equiv="refresh" content="30">-->

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
    <div class="container-fluid">

<?php } ?>

    <div class="page-header mt-3 mb-5">
        <h1>GPU Status <small class="d-none d-sm-inline">(Refreshed every 30 seconds)</small><a href="https://gitlab.criteois.com/rat/gpu-monitor" style="float:right"><img src="css/gitlab_logo_white.svg" height="20px"></a></h1>
    </div>
    <div class="mb-3 text-right">
        <button id="btn_collapse_all" class="btn btn-secondary btn-sm" aria-expanded="true"></button>
    </div>

<?php

if (is_file("data/hosts.json"))
    $HOSTS = json_decode(file_get_contents("data/hosts.json"), true);
else
    $HOSTS = array();

ksort($HOSTS);

$SHORT_GPU_NAMES = array(
	"Tesla M40 24GB" => "Tesla M40",
        "Tesla V100-PCIE-16GB" => "Tesla V100");
$SHORTER_GPU_NAMES = array(
	"Tesla M40 24GB" => "M40",
        "Tesla V100-PCIE-16GB" => "Tesla V100");
$GPU_COLS_LIST = array("index", "uuid", "name", "memory.used", "memory.total", "utilization.gpu", "utilization.memory", "temperature.gpu", "timestamp");
$GPU_PROC_LIST = array("timestamp", "gpu_uuid", "used_gpu_memory", "process_name", "pid");
$CPU_COLS_LIST = array("average_use","total_nb_proc");

date_default_timezone_set("UTC");

if (is_file("data/comments.json"))
    $COMMENTS = json_decode(file_get_contents("data/comments.json"), true);
else
    $COMMENTS = array();

if (isset($_POST["host"]) && isset($HOSTS[$_POST["host"]])) {
    try {
        if (isset($_POST["reset"])) throw new Exception("reset");
        $name = preg_replace('/[^\p{L}\p{N}-_.,+\/#&]/u', " ", $_POST["name"]);
        $comment = preg_replace('/[^\p{L}\p{N}-_.,+\/#&]/u', " ", $_POST["comment"]);
        if (preg_match('/^ *(([0-9]+)d)? ?(([0-9]+)h)? *$/i', $_POST["date"], $matches)) {
            $interv = new DateInterval("P".($matches[2] ? $matches[2] : 0)."DT".(isset($matches[4]) ? $matches[4] : 0)."H");
            $date = new DateTime();
            $date->add($interv);
        }
        else {
            $date = new DateTime($_POST["date"]);
        }
        $date = $date->format("Y-m-d H:i");
    }
    catch (Exception $e) {
        $name = "";
        $date = "";
        $comment = "";
    }
    $index = (((int) $_POST["id"]) + 20) % 20; // limit btwn 0 and 19

    $COMMENTS[$_POST["host"]][$index] = array("name" => $name, "date" => $date, "comment" => $comment);

    file_put_contents("data/comments.json", json_encode($COMMENTS));
}

foreach ($HOSTS as $hostname => $hosttitle) {

    $gpus = array();
    $time = false;

    foreach(file('data/'.$hostname.'_gpus.csv') as $gpu) {
        $gpu = str_getcsv($gpu);
        if (count($gpu) != count($GPU_COLS_LIST))
            continue;
        $gpu = array_combine($GPU_COLS_LIST, array_map('trim', $gpu));
        $gpu["index"] = (int) $gpu["index"];
        $gpu["memory.used"] = (int) $gpu["memory.used"];
        $gpu["memory.total"] = (int) $gpu["memory.total"];
        $gpu["memory"] = round($gpu["memory.used"] * 100.0 / $gpu["memory.total"]);
        $gpu["utilization.gpu"] = (int) $gpu["utilization.gpu"];
        $gpu["utilization.memory"] = (int) $gpu["utilization.memory"];
        $gpu["temperature.gpu"] = (int) $gpu["temperature.gpu"];
        $gpu["processes"] = array();

        $gpus[$gpu["uuid"]] = $gpu;

        $time = $gpu["timestamp"]; // save time, keeps the latest
    }
    uasort($gpus, function($a, $b) { return $a["index"] - $b["index"]; });

    $users = array();
    $users_childs = array();

    $first = true;
    foreach(file('data/'.$hostname.'_users.csv') as $user) {
        if ($first) {
            $users_childs = json_decode($user, true);
            $first = false;
            continue;
        }
        $user = array_map('trim', str_getcsv(trim($user), " "));
        $users[$user[0]] = array("user" => $user[1], "time" => join(array_slice($user, 2), " "));
    }


    $last_process_time = 0;
    foreach(file('data/'.$hostname.'_processes.csv') as $process) {
        $process = str_getcsv($process);
        if (count($process) != count($GPU_PROC_LIST))
            continue;
        $process_time = strtotime($process[0]);
        if ($last_process_time < $process_time)
            $last_process_time = $process_time;
    }

    foreach(file('data/'.$hostname.'_processes.csv') as $process) {
        $process = str_getcsv($process);
        if (count($process) != count($GPU_PROC_LIST))
            continue;
        $process = array_combine($GPU_PROC_LIST, array_map('trim', $process));

        // 5sec before last info (probably previous loop) or 1min old => exclude
        $process_time = strtotime($process["timestamp"]);
        if ($last_process_time - $process_time > 3 || time() - $process_time > 60)
            continue;

        // get more process info from `ps` data
        $process["user"] = "???"; $process["time"] = "???"; $process["alert"] = "This process is probably dead";
        if (isset($users[$process["pid"]])) {
            $process["user"] = $users[$process["pid"]]["user"];
            $process["time"] = $users[$process["pid"]]["time"];
            $process["alert"] = false;
        }
        elseif (isset($users_childs[$process["pid"]][0]) && isset($users[$users_childs[$process["pid"]][0]])) {
            $process["user"] = $users[$users_childs[$process["pid"]][0]]["user"];
            $process["time"] = $users[$users_childs[$process["pid"]][0]]["time"];
            $process["alert"] .= ". Kill childs PIDs: ".implode($users_childs[$process["pid"]], ", ");
        }
        $process["usage"] = round(($process['used_gpu_memory']+0.001) / ($gpus[$process["gpu_uuid"]]['memory.total']+0.001) * 100);
        // if the process does not appear in ps and the gpu is not used, the process is probably dead but still appearing here because no running process was added by nvidia-smi
        if (!$users[$process["pid"]] && $gpus[$process["gpu_uuid"]]['memory.used'] < 10)
            continue;
        $gpus[$process["gpu_uuid"]]["processes"][$process["pid"]] = $process;
    }

    $disks = array(
        "/" => array("total" => 0, "used" => 0, "usage" => 0),
        "/var/opt" => array("total" => 0, "used" => 0, "usage" => 0)
    );

    $f = fopen('data/'.$hostname.'_status.csv', "r");
    $diskRaw = fgets($f);
    if (substr($diskRaw, 0, 3) != "Mem") {
        $diskRaw = preg_split("#\s+#", $diskRaw);
        $disks["/"] = array(
                "total" => round($diskRaw[1]/1024/1024, -1),
                "used" => round($diskRaw[2]/1024/1024, -1),
                "usage" => round(($diskRaw[2]+0.001) / ($diskRaw[1]+0.001) * 100));
        $ramRaw = fgets($f);
    }

    if(file_exists('data/'.$hostname.'_disks.csv')){
        $disks = parse_df_file('data/'.$hostname.'_disks.csv');
    }

    preg_match("#^[^ ]+ +([^ ]+) +([^ ]+)#", $ramRaw, $ramRaw);
    $ram = array("total" => round($ramRaw[1] / 1024), "used" => round($ramRaw[2] / 1024), "usage" => round(($ramRaw[2]+0.001) / ($ramRaw[1]+0.001) * 100));

    //// based on top
    //$cpuRaw = fgets($f);
    //preg_match("#[ ,]([0-9,.]+) id#", $cpuRaw, $cpuRaw);
    //$cpu = 100 - round((float)str_replace(",",".", $cpuRaw[1]));

    $nbCpu = (int) fgets($f);
    $uptime = fgets($f);
    preg_match("#load average: ([0-9\,.]+), #", $uptime, $uptime);
    $cpu = round((float)str_replace(",",".", $uptime[1]) / $nbCpu * 100);

    fclose($f);

    $deltaTSec = (strtotime($time) - time());
    $deltaT = abs($deltaTSec);
    $deltaTUnit = 's';
    $deltaTDirection = ($deltaTSec <= 0) ? ' ago' : 'in the future';
    if ($deltaT >= 60) {
        $deltaT = $deltaT / 60;
        $deltaTUnit = ' min';
        if ($deltaT >= 60) {
            $deltaT = $deltaT / 60;
            $deltaTUnit = ' hours';
            if ($deltaT >= 24) {
                $deltaT = $deltaT / 24;
                $deltaTUnit = ' days';
            }
        }
    }

    ?>

    <div class="server-panel">
        <div class="server bg-dark p-3 text-light">
            <div class="d-flex d-row align-items-center">
                <h5 class="mb-0 flex-grow-1">
                    <?php if ($deltaTSec < -500) { ?>
                        <span class="badge badge-pill badge-danger mr-2" data-toggle="tooltip" data-placement="top" title="Data is not up to date for this server">
                            <i class="material-icons">warning</i>
                        </span>
                    <?php } ?>
                    <?php echo $hosttitle; ?>
                </h5>
                <div class="server-refresh mr-3">
                    <i class="material-icons">access_time</i>
                    <?php echo round($deltaT).$deltaTUnit.$deltaTDirection ?>
                </div>
                <button class="btn btn-light btn-sm btn-icon" data-toggle="collapse" data-target="#content_<?php echo $hostname ?>" aria-expanded="true" aria-controls="content_<?php echo $hostname ?>">
                    <i class="material-icons icon-collapse"></i>
                </button>
            </div>
            <div class="row mt-3">
                <div class="col d-flex">
                    <?php
                    $bar_status = "success";
                    if ($ram["usage"] > 35) $bar_status = "warning";
                    if ($ram["usage"] > 70) $bar_status = "danger";
                    ?>
                    <span class="server-prefix badge badge-secondary">RAM</span>
                    <div class="progress w-100" data-toggle="tooltip" data-placement="top" title="<?php printf("%d/%d Go", $ram['used'], $ram['total']); ?>">
                        <div class="progress-bar bg-<?php echo $bar_status ?>" role="progressbar" style="width: <?php echo $ram["usage"] ?>%;" aria-valuenow="<?php echo $ram["usage"] ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $ram["usage"] ?>%</div>
                    </div>
                </div>
                <div class="col d-flex">
                    <?php
                    $bar_status = "success";
                    if ($cpu > 35) $bar_status = "warning";
                    if ($cpu > 70) $bar_status = "danger";
                    ?>
                    <span class="server-prefix badge badge-secondary">CPU</span>
                    <div class="progress w-100" data-toggle="tooltip" data-placement="top" title="A score > 100% means processes are waiting">
                        <div class="progress-bar bg-<?php echo $bar_status ?>" role="progressbar" style="width: <?php echo $cpu ?>%;" aria-valuenow="<?php echo $cpu ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $cpu ?>%</div>
                    </div>
                </div>
                <div class="col d-flex">
                    <?php
                    $bar_status = "success";
                    $disk_name = "/";
                    $used = $disks[$disk_name]["used"];
                    $total = $disks[$disk_name]["total"];
                    $usage = $disks[$disk_name]["usage"];
                    if ($usage > 35) $bar_status = "warning";
                    if ($usage > 70) $bar_status = "danger";
                    ?>
                    <span class="server-prefix badge badge-secondary"><?php echo $disk_name ?></span>
                    <div class="progress w-100" data-toggle="tooltip" data-placement="top" title="<?php printf("%d/%d Go", $used, $total); ?>">
                        <div class="progress-bar bg-<?php echo $bar_status ?>" role="progressbar" style="width: <?php echo $usage ?>%;" aria-valuenow="<?php echo $usage ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $usage ?>%</div>
                    </div>
                </div>
                <div class="col d-flex">
                    <?php
                    $bar_status = "success";
                    $disk_name = "/var/opt";
                    $used = $disks[$disk_name]["used"];
                    $total = $disks[$disk_name]["total"];
                    $usage = $disks[$disk_name]["usage"];
                    if ($usage > 35) $bar_status = "warning";
                    if ($usage > 70) $bar_status = "danger";
                    ?>
                    <span class="server-prefix badge badge-secondary"><?php echo $disk_name ?></span>
                    <div class="progress w-100" data-toggle="tooltip" data-placement="top" title="<?php printf("%d/%d Go", $used, $total); ?>">
                        <div class="progress-bar bg-<?php echo $bar_status ?>" role="progressbar" style="width: <?php echo $usage ?>%;" aria-valuenow="<?php echo $usage ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $usage ?>%</div>
                    </div>
                </div>
            </div>
        </div>
        <div id="content_<?php echo $hostname ?>" class="panel-container collapse show">
            <div class="px-3 pt-3">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="col-index">#</th>
                            <th scope="col" class="col-name">Name</th>
                            <th scope="col" class="col-memory">Memory</th>
                            <th scope="col" class="col-gpu">GPU</th>
                            <th scope="col" class="col-reservation">Reservation</th>
                            <th scope="col"><span class="d-none d-sm-inline">Processes <span class="badge badge-pill badge-secondary">pid@user (RAM)</span></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gpus as $gpu) { ?>
                        <tr>
                            <th scope="row" class="col-index"><?php echo $gpu['index']; ?></th>
                            <td class="col-name">
                                <span class="d-none d-sm-inline"><?php echo $SHORT_GPU_NAMES[$gpu['name']] ? '<span data-toggle="tooltip" title="'.$gpu['name'].'">'.$SHORT_GPU_NAMES[$gpu['name']].'</span>' : $gpu['name']; ?></span>
                                <span class="d-inline d-sm-none"><?php echo $SHORTER_GPU_NAMES[$gpu['name']] ? '<span data-toggle="tooltip" title="'.$gpu['name'].'">'.$SHORTER_GPU_NAMES[$gpu['name']].'</span>' : $gpu['name']; ?></span>
                                (<?php echo round($gpu['memory.total'] / 1000) ?> Go)
                            </td>
                            <td class="align-middle col-memory">
                                <?php
                                $bar_status = "success";
                                if ($gpu['memory'] > 20) $bar_status = "warning";
                                if ($gpu['memory'] > 60) $bar_status = "danger";
                                ?>
                                <div class="progress" data-toggle="tooltip" data-placement="top" title="<?php echo $gpu['memory.used'].'/'.$gpu['memory.total']; ?> Mo / Access rate: <?php echo $gpu["utilization.memory"] ?>%">
                                    <div class="progress-bar bg-<?php echo $bar_status ?>" role="progressbar" style="width: <?php echo $gpu['memory'] ?>%;" aria-valuenow="<?php echo $gpu['memory'] ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $gpu['memory'] ?>%</div>
                                </div>
                            </td>
                            <td class="align-middle col-gpu">
                                <?php
                                $bar_status = "success";
                                if ($gpu['utilization.gpu'] > 20) $bar_status = "warning";
                                if ($gpu['utilization.gpu'] > 60) $bar_status = "danger";
                                ?>
                                <div class="progress" data-toggle="tooltip" data-placement="top" title="<?php echo $gpu['temperature.gpu']; ?> °C">
                                    <div class="progress-bar bg-<?php echo $bar_status ?>" role="progressbar" style="width: <?php echo $gpu['utilization.gpu'] ?>%;" aria-valuenow="<?php echo $gpu['utilization.gpu'] ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $gpu['utilization.gpu'] ?>%</div>
                                </div>
                            </td>

                            <?php
                            try {
                                $comment = $COMMENTS[$hostname][$gpu['index']];

                                $date = date_create($comment["date"]);
                                $now = date_create();
                                if ($date > $now)
                                    $now->sub(new DateInterval("PT1H")); // remove 1h from now to round up diff to ceil instead of floor
                                $diff = date_diff($now, $date);
                                if ($diff->days >= 1)
                                    $diff_disp = $diff->format("%ad");
                                else
                                    $diff_disp = $diff->format("%hh");

                                if ($date < $now && $diff->days > 2)
                                    throw new Exception("remove, too old");
                            }
                            catch (Exception $e) {
                                $comment = array("date" => "", "name" => "", "comment" => ""); }
                            ?>
                            <td class="td-comment text-right col-reservation" data-name="<?php echo $comment["name"] ?>" data-comment="<?php echo $comment["comment"] ?>" data-date="<?php echo $comment["date"] ?>" data-host="<?php echo $hostname ?>" data-id="<?php echo $gpu['index'] ?>">
                                <?php if ($comment["date"] && $comment["name"]) { ?>
                                    <span class="d-inline-flex align-items-center badge badge-<?php echo ($date > $now) ? "danger" : "secondary"; ?>" data-toggle="tooltip" data-placement="top" title="<?php echo $comment["comment"]; ?>">
                                        <?php
                                            echo $comment["name"].' ('.$diff_disp.($date > $now ? "" : " ago").')';
                                            if ($comment["comment"]) echo '&nbsp;&nbsp;<i class="material-icons">mode_comment</i>';
                                        ?>
                                    </span>
                                <?php } ?>
                                <button class="reservation-btn btn btn-icon btn-sm btn-primary"><i class="material-icons">edit</i></button>
                            </td>
                            <td class="text-right">
                                <span class="d-none d-sm-inline process-content">
                                <?php foreach ($gpu["processes"] as $process) { ?>
                                    <?php
                                    $process_status = "secondary";
                                    if ($process["usage"] > 15) $process_status = "info";
                                    if ($process["usage"] > 40) $process_status = "primary";
                                    if ($process["alert"] !== false) $process_status = "danger";
                                    ?>
                                    <span class="process badge badge-<?php echo $process_status ?>" data-toggle="tooltip" data-placement="top" title="<?php if ($process["alert"]) echo $process["alert"]; ?> <?php echo $process['process_name'] ?> (Mem: <?php echo $process['used_gpu_memory'] ?> Mo) / Started: <?php echo $process['time'] ?>"><?php echo $process["pid"].'@<span class="user">'.$process["user"] ?></span> (<?php echo $process["usage"] ?>%)</span>
                                <?php } ?>
                                </span>
                                <span class="d-inline d-sm-none">
                                    <a type="button" tabindex="0" role="button" class="btn btn-icon btn-sm btn-dark btn-process"><i class="material-icons">arrow_drop_down</i></a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php } // close foreach $HOSTS ?>


<!-- Reservation modal -->
<div class="modal fade" id="reservationModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
    <form class="comment-form" method="POST" url="?">
        <input type="hidden" name="id" id="idInput" class="form-id">
        <input type="hidden" name="host" id="hostInput" class="form-host">
        <div class="modal-header">
            <h5 class="modal-title">Make a reservation</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <i class="material-icons">close</i>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="nameInput">Name</label>
                <input type="text" class="form-control" id="nameInput" name="name">
            </div>
            <div class="form-group">
                <label for="dateInput">Date</label>
                <input type="text" class="form-control" id="dateInput" name="date">
                <small>Format: date (YYYY-MM-DD HH:MM) or duration (<i>x</i>d <i>y</i>h)</small>
            </div>
            <div class="form-group">
                <label for="commentTextarea">Comment</label>
                <textarea class="form-control" id="commentTextarea" rows="3" name="comment"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" name="save" class="btn btn-primary">Save</button>
            <button type="submit" name="reset" class="btn btn-danger"><i class="material-icons">delete</i></button>
        </div>
    </div>
    </form>
  </div>
</div>


    <?php if (!isset($_GET["content"])) { ?>

    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="js/bootstrap.min.js"></script>
    <script type="text/javascript">
    function preparePage () {
        $('[data-toggle="tooltip"]').tooltip();

        $('.btn-process').popover({
            placement: 'top',
            container: 'body',
            html: true,
            //selector: '[rel="popover"]', //Sepcify the selector here
            content: function () {
                var data = $(this).parent().parent().find(".process-content").html();
                if (data.trim() == "") data = "<small>No&nbsp;process</small>"
                return data;
            },
            trigger: "focus"
        });

        $(".reservation-btn").click(function(){
            var parent = $(this).parent();
            $('#nameInput').val(parent.data('name'));
            $('#dateInput').val(parent.data('date'));
            $('#commentTextarea').val(parent.data('comment'));
            $('#idInput').val(parent.data('id'));
            $('#hostInput').val(parent.data('host'));
            $("#reservationModal").modal("show");
        });

        $('#btn_collapse_all').click(function() {
            $('.collapse').collapse('toggle');
            $(this).attr('aria-expanded', ($(this).attr('aria-expanded') == 'true' ? 'false' : 'true'));
        });
    }
    $(preparePage);

    window.setInterval(function() {
        if (!($('#reservationModal').hasClass('show'))) {
            $.get("?content", {}, function (data) {
                $('.container-fluid').html(data);
                preparePage();
            })
        }
    }, 300000);
    </script>
  </body>
</html>

<?php } ?>
