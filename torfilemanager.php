<?php
namespace TorFileManager;

class Config
{
    static public $folder_img = 'http://findicons.com/files/icons/552/aqua_candy_revolution/16/security_folder_black.png';
    static public $file_img = 'http://findicons.com/files/icons/743/rumax_ip/16/registry_file.png';
    static public $date_format = 'd M y H:i:s';
    static public $ds = DIRECTORY_SEPARATOR;
}

class Processing
{
    public static function replaceSeparators($address = '')
    {
        return str_replace(['//','\\'], ['/','/'], str_replace(Config::$ds, '/', $address));
    }
}

class FileManager
{
    public static function getRootFolder()
    {
        return Processing::replaceSeparators($_SERVER['DOCUMENT_ROOT']);
    }

    public static function getFolders($path = '')
    {
        $folders = [];
        foreach (new \DirectoryIterator($path) as $folder) {
            if (!$folder->isDot() && $folder->isDir()) {
                $folder_info = new \stdClass();
                $folder_info->name = $folder->getFilename();
                $folder_info->size = $folder->getSize();
                $folder_info->type = $folder->getType();
                $folder_info->owner = $folder->getOwner();
                $folder_info->perms = substr(sprintf('%o', $folder->getPerms()), -4);
                $folder_info->ctime = date(Config::$date_format, $folder->getCTime());
                $folder_info->atime = date(Config::$date_format, $folder->getATime());
                $folder_info->mtime = date(Config::$date_format, $folder->getMTime());
                $folder_info->fileinfo = $folder->getFileInfo();
                $folder_info->isr = $folder->isReadable();
                $folder_info->isw = $folder->isWritable();
                $folder_info->ise = $folder->isExecutable();
                $folders[] = $folder_info;
            }
        }
        return $folders;
    }

    public static function getFiles($path = '')
    {
        $files = [];
        foreach (new \DirectoryIterator($path) as $file) {
            if (!$file->isDot() && $file->isFile()) {
                $file_info = new \stdClass();
                $file_info->name = $file->getFilename();
                $file_info->size = $file->getSize();
                $file_info->type = $file->getType();
                $file_info->owner = $file->getOwner();
                $file_info->perms = substr(sprintf('%o', $file->getPerms()), -4);
                $file_info->ctime = date(Config::$date_format, $file->getCTime());
                $file_info->atime = date(Config::$date_format, $file->getATime());
                $file_info->mtime = date(Config::$date_format, $file->getMTime());
                $file_info->fileinfo = $file->getFileInfo();
                $file_info->isr = $file->isReadable();
                $file_info->isw = $file->isWritable();
                $file_info->ise = $file->isExecutable();
                $files[] = $file_info;
            }
        }
        return $files;
    }
}

$doc_root = FileManager::getRootFolder();

$path = $doc_root;
$pre_folders = [];

if (isset($_REQUEST['prefolders'])) {
    $pre_folders = explode(',', $_REQUEST['prefolders']);
}

if (isset($_REQUEST['folder'])) {
    $pre_folders[] = $_REQUEST['folder'];
}

if (isset($_REQUEST['folder'])) {
    $path_folder = $_REQUEST['folder'];
    $path = $doc_root . Config::$ds;
    if (sizeof($pre_folders)) {
        $path .= implode(Config::$ds, $pre_folders);
    }else{

        $path .= $path_folder;
    }
    //echo $path; die;
    if (!file_exists($path)) {
        $path = $doc_root;
    }
}

$path = Processing::replaceSeparators($path);
$folders = FileManager::getFolders($path);
$files = FileManager::getFiles($path);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TorFileManager</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css"
          integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css"
          integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">
    <style type="text/css">
        .jumbotron .table tr td a img {
            margin-right: 3px;
        }
    </style>
</head>
<body>

<div class="container theme-showcase" role="main">
    <div class="panel panel-info">
        <div class="panel-heading">Current path: <strong><?= $path ?></strong></div>
        <div class="panel-body">
            <span class="label label-default">Folders: <?= count($folders); ?></span>
            <span class="label label-info">Files: <?= count($files); ?></span>
        </div>
    </div>
    <div class="jumbotron">
        <table class="table table-hover">
            <tr>
                <th>Title</th>
                <th>Created</th>
                <th>Owner/Permissions [Read|Write|Execute]</th>
                <th>Modified</th>
            </tr>
            <?php
            foreach ($folders as $folder) {
                ?>
                <tr>
                    <td>
                        <a href="?folder=<?= $folder->name; ?><?= count($pre_folders)?('&prefolders='.implode(',', $pre_folders)):''; ?>">
                            <img src="<?= Config::$folder_img; ?>"
                                 alt="<?= $folder->type . ': ' . $folder->name; ?>"/><?= $folder->name; ?>
                        </a>
                    </td>
                    <td><?= '<span class="label label-default">' . $folder->ctime . '</span> '?></td>
                    <td><?= '<span class="label label-primary">' . $folder->owner . '</span> <span class="label label-info">' . $folder->perms . '</span>'; ?>
                        [ <?= ($folder->isr ? '<span class="label label-success">R</span>' : '<span class="label label-default">UR</span>')
                        . ' | ' .
                        ($folder->isw ? '<span class="label label-success">W</span>' : '<span class="label label-default">UW</span>')
                        ?>
                        ]
                    </td>
                    <td><?= ' <span class="label label-default">' . $folder->mtime . '</span>' ?></td>
                </tr>
            <?php
            }
            foreach ($files as $file) {
                ?>
                <tr>
                    <td>
                        <a href="?file=<?= $file->name; ?>">
                            <img src="<?= Config::$file_img; ?>"
                                 alt="<?= $file->type . ': ' . $file->name; ?>"/><?= $file->name; ?>
                        </a>
                    </td>
                    <td><?= '<span class="label label-default">' . $file->ctime . '</span> '?></td>
                    <td><?= '<span class="label label-primary">' . $file->owner . '</span> <span class="label label-info">' . $file->perms . '</span>'; ?>
                        [ <?= ($file->isr ? '<span class="label label-success">R</span>' : '<span class="label label-default">UR</span>')
                        . ' | ' .
                        ($file->isw ? '<span class="label label-success">W</span>' : '<span class="label label-warning">UW</span>')
                        . ' | ' .
                        ($file->ise ? '<span class="label label-danger">E</span>' : '<span class="label label-default">UE</span>') ?>
                        ]
                    </td>
                    <td><?= ' <span class="label label-default">' . $file->mtime . '</span>' ?></td>
                </tr>
            <?php
            }
            ?>
        </table>
    </div>
</div>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"
        integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS"
        crossorigin="anonymous"></script>
</body>
</html>