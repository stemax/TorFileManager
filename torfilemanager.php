<?php
namespace TorFileManager;

class Config
{
    static public $folder_img = 'http://findicons.com/files/icons/552/aqua_candy_revolution/16/security_folder_black.png';
    static public $file_img = 'http://findicons.com/files/icons/743/rumax_ip/16/registry_file.png';
    static public $download_img = 'http://findicons.com/files/icons/141/toolbar_icons_6_by_ruby_softwar/16/download.png';
    static public $zip_img = 'http://findicons.com/files/icons/1156/fugue/16/folder_zipper.png';
    static public $unzip_img = 'http://findicons.com/files/icons/1016/aerovista/16/comprimidos_zip.png';
    static public $date_format = 'd M y H:i:s';
    static public $ds = DIRECTORY_SEPARATOR;
}

class Processing
{
    public static function replaceSeparators($address = '')
    {
        return str_replace(['//', '\\'], ['/', '/'], str_replace(Config::$ds, '/', $address));
    }

    public static function formatBytes($bytes, $precision = 2)
    {
        if (!$bytes) return '0 b';
        $base = log($bytes, 1024);
        $suffixes = array('b', 'Kb', 'Mb', 'Gb', 'Tb');
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }

    public static function sortArrayWithObjects($array, $property)
    {
        usort($array, function ($a, $b) {
            if ($a->name == $b->name) {
                return 0;
            }
            return ($a->name < $b->name) ? -1 : 1;
        });
        return $array;
    }
}

class FileManager
{
    private static $errors = [];
    private static $messages = [];

    public static function getRootFolder()
    {
        return Processing::replaceSeparators($_SERVER['DOCUMENT_ROOT']);
    }

    public static function getFolders($path = '')
    {
        $folders = [];
        foreach (new \DirectoryIterator($path) as $folder) {
            try {
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
            } catch (\RuntimeException $e) {
                self::$errors[] = 'Error access to: ' . $folder->getFilename();
            }
        }
        return $folders;
    }

    public static function getFiles($path = '')
    {
        $files = [];
        foreach (new \DirectoryIterator($path) as $file) {
            try {
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
                    $file_info->ext = $file->getExtension();
                    $files[] = $file_info;
                }
            } catch (\RuntimeException $e) {
                //echo '<div class="alert alert-warning" role="alert">Error access to: '.$file->getFilename().'</div>';
            }
        }
        return $files;
    }

    public static function downloadFile($file)
    {
        if (file_exists($file)) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        } else {
            self::$errors[] = 'Download: Not found ' . $file;
            return false;
        }
    }

    public static function convertFolderToZip($folder_path = '', $zip_path = '')
    {
        if (!$zip_path) {
            $zip_path = self::getRootFolder() . '/zip_files';
            if (!file_exists($zip_path)) {
                if (!mkdir($zip_path, 0777)) {
                    self::$errors[] = 'ZIP: I couldn\'t create zip files folder ' . $zip_path;
                    return false;
                }
            }
        }
        //echo $folder_path.'<br/>';
        $zip = new \ZipArchive();
        $zip_name = substr($folder_path, (strrpos($folder_path, '/') + 1)) . '.zip';
        //echo $zip_name.'<br/>';
        if (file_exists($zip_path . '/' . $zip_name)) unlink($zip_path . '/' . $zip_name);
        $ret = $zip->open($zip_path . '/' . $zip_name, \ZipArchive::OVERWRITE | \ZipArchive::CREATE | \ZipArchive::EXCL);
        if ($ret !== TRUE) {
            self::$errors[] = 'ZIP: Failed with code ' . $ret . ($zip->getStatusString());
        } else {
            $first_folders = self::getFolders($folder_path);
            if (sizeof($first_folders)) {
                foreach ($first_folders as $ff) {
                    $zip->addEmptyDir($ff->name);
                    $zip = self::addSubFolderContentToZip($folder_path, $ff->name, '', $zip);
                }
            }
            $first_files = self::getFiles($folder_path);
            if (sizeof($first_files)) {
                foreach ($first_files as $ff) {
                    $zip->addFile($folder_path . '/' . $ff->name, $ff->name);
                }
            }
            self::$messages[] = "ZIP: $folder_path transfer to $zip_path";

            $zip->close();
        }
    }

    protected static function addSubFolderContentToZip($folder_path, $active_folder, $pre_path, $zip)
    {
        //echo 'Work with subfolder: ' . $folder_path . '/' . $active_folder . ' <br/>';
        $pre_path .= ($pre_path ? '/' : '') . $active_folder;
        $folders = self::getFolders($folder_path . '/' . $active_folder);
        if (sizeof($folders)) {
            foreach ($folders as $ff) {
                //echo 'Added : ' . $pre_path . '/' . $ff->name . ' <br/>';
                $zip->addEmptyDir($pre_path . '/' . $ff->name);
                //echo 'Continue parsing to  : ' . $folder_path . '/' . $active_folder . '/' . $ff->name . ' <br/>';
                $zip = self::addSubFolderContentToZip($folder_path . '/' . $active_folder, $ff->name, $pre_path, $zip);
            }
        }
        $files = self::getFiles($folder_path . '/' . $active_folder);
        if (sizeof($files)) {
            foreach ($files as $ff) {
                //echo 'Added : ' . $pre_path . '/' . $ff->name . ' <br/>';
                $zip->addFile($folder_path . '/' . $active_folder .'/'. $ff->name, $pre_path . '/' . $ff->name);
            }
        }
        return $zip;
    }

    public static function extractZip($file_path = '', $extract_path = '')
    {
        if (!file_exists($file_path) || !is_file($file_path)) {
            self::$errors[] = 'ZIP: Not found in ' . $file_path;
            return false;
        }
        if (!$extract_path) {
            $extract_path = self::getRootFolder() . '/zip_extract';
            if (!file_exists($extract_path)) {
                if (!mkdir($extract_path, 0755)) {
                    self::$errors[] = 'ZIP: I couldn\'t create extract folder ' . $extract_path;
                    return false;
                }
            }
        }
        $zip = new \ZipArchive;
        $res = $zip->open($file_path);
        if ($res === TRUE) {
            $zip->extractTo($extract_path);
            $zip->close();
            self::$messages[] = "ZIP: $file_path extracted to $extract_path";
        } else {
            self::$errors[] = 'ZIP: I couldn\'t open' . $file_path;
            return false;
        }
    }

    public static function getErrorsString()
    {
        $error_string = '';
        if (sizeof(self::$errors)) foreach (self::$errors as $error) {
            $error_string .= '<div class="alert alert-warning" role="alert">' . $error . '</div>';
        }
        return $error_string;
    }

    public static function getMessagesString()
    {
        $messages_string = '';
        if (sizeof(self::$messages)) foreach (self::$messages as $message) {
            $messages_string .= '<div class="alert alert-success" role="success">' . $message . '</div>';
        }
        return $messages_string;
    }
}


$path = $action = '';
$path_file = $path_folder = '';
$home_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
$up_url = '';
$pre_folders = [];

$doc_root = FileManager::getRootFolder();

//Initialise variables from REQUEST
if (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];

    if (isset($_REQUEST['file'])) {
        $path_file = $_COOKIE['TOR_PATH'] . '/' . $_REQUEST['file'];
    }
}

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
    } else {

        $path .= $path_folder;
    }

    if (!@file_exists($path)) {
        $path = $doc_root;
    }

    if (sizeof($pre_folders)) {
        $up_folders = $pre_folders;
        array_pop($up_folders);
        $up_folder = array_pop($up_folders);
        if ($up_folder) {
            $up_url = '?folder=' . $up_folder . (count($up_folders) ? ('&prefolders=' . implode(',', $up_folders)) : '');
        } else {
            $up_url = $home_url;
        }
    }

} else $path = $doc_root;

//Switch actions:
switch ($action) {
    case 'download':
        FileManager::downloadFile($path_file);
        break;
    case 'to_zip':
        if (isset($_REQUEST['zipfolder'])) {
            $folder_path = $_COOKIE['TOR_PATH'] . '/' . $_REQUEST['zipfolder'];
            FileManager::convertFolderToZip($folder_path);
        }
        break;
    case 'extract_zip':
        FileManager::extractZip($path_file);
        break;
    default:
        break;
}
$path = Processing::replaceSeparators($path);
setcookie("TOR_PATH", $path);
$folders = Processing::sortArrayWithObjects(FileManager::getFolders($path), 'name');
$files = Processing::sortArrayWithObjects(FileManager::getFiles($path), 'name');
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
    <nav class="navbar navbar-default">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse"
                        data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="<?= $home_url; ?>">TFM</a>
            </div>
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    <li><a href="<?= $home_url; ?>"><span class="glyphicon glyphicon-home" aria-hidden="true"></span>
                            Home<span class="sr-only">(current)</span></a>
                    </li>
                    <?php if ($up_url) {
                        ?>
                        <li><a href="<?= $up_url; ?>"><span class="glyphicon glyphicon-circle-arrow-up"
                                                            aria-hidden="true"></span> Up</a></li>
                    <?php
                    }?>
                    <li><a href="javascript:void(0)" onclick="location.reload();"><span
                                class="glyphicon glyphicon-refresh" aria-hidden="true"></span> Refresh</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="panel panel-info">
        <div class="panel-heading">Current path: <strong><?= $path ?></strong></div>
        <div class="panel-body">
            <span class="label label-default">Folders: <?= count($folders); ?></span>
            <span class="label label-info">Files: <?= count($files); ?></span>
        </div>
    </div>
    <?= FileManager::getErrorsString(); ?>
    <?= FileManager::getMessagesString(); ?>
    <div class="jumbotron">
        <table class="table table-hover">
            <tr>
                <th>Title</th>
                <th>Created</th>
                <th>Size</th>
                <th>Owner/Permissions [Read|Write|Execute]</th>
                <th>Modified</th>
                <th>Download/Zip</th>
            </tr>
            <?php
            foreach ($folders as $folder) {
                ?>
                <tr>
                    <td>
                        <a href="?folder=<?= $folder->name; ?><?= count($pre_folders) ? ('&prefolders=' . implode(',', $pre_folders)) : ''; ?>">
                            <img src="<?= Config::$folder_img; ?>"
                                 alt="<?= $folder->type . ': ' . $folder->name; ?>"/><?= $folder->name; ?>
                        </a>
                    </td>
                    <td><?= '<span class="label label-default">' . $folder->ctime . '</span> ' ?></td>
                    <td><?= '<span class="label label-success">' . Processing::formatBytes($folder->size) . '</span> ' ?></td>
                    <td><?= '<span class="label label-primary">' . $folder->owner . '</span> <span class="label label-info">' . $folder->perms . '</span>'; ?>
                        [ <?= ($folder->isr ? '<span class="label label-success">R</span>' : '<span class="label label-default">UR</span>')
                        . ' | ' .
                        ($folder->isw ? '<span class="label label-success">W</span>' : '<span class="label label-default">UW</span>')
                        ?>
                        ]
                    </td>
                    <td><?= ' <span class="label label-default">' . $folder->mtime . '</span>' ?></td>
                    <td>
                        <a href="?action=to_zip&zipfolder=<?= $folder->name; ?>">
                            <img src="<?= Config::$zip_img; ?>" alt="<?= $folder->type . ': ' . $folder->name; ?>"/>
                        </a>
                    </td>
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
                    <td><?= '<span class="label label-default">' . $file->ctime . '</span> ' ?></td>
                    <td><?= '<span class="label label-success">' . Processing::formatBytes($file->size) . '</span> ' ?></td>
                    <td><?= '<span class="label label-primary">' . $file->owner . '</span> <span class="label label-info">' . $file->perms . '</span>'; ?>
                        [ <?= ($file->isr ? '<span class="label label-success">R</span>' : '<span class="label label-default">UR</span>')
                        . ' | ' .
                        ($file->isw ? '<span class="label label-success">W</span>' : '<span class="label label-warning">UW</span>')
                        . ' | ' .
                        ($file->ise ? '<span class="label label-danger">E</span>' : '<span class="label label-default">UE</span>') ?>
                        ]
                    </td>
                    <td><?= ' <span class="label label-default">' . $file->mtime . '</span>' ?></td>
                    <td>
                        <a href="?action=download&file=<?= $file->name; ?>"><img
                                src="<?= Config::$download_img; ?>" alt="<?= $file->type . ': ' . $file->name; ?>"/></a>
                        <?php if ($file->ext == 'zip') { ?>
                            <a href="?action=extract_zip&file=<?= $file->name; ?>"><img
                                    src="<?= Config::$unzip_img; ?>"
                                    alt="<?= $folder->type . ': ' . $folder->name; ?>"/></a>
                        <?php } ?>
                    </td>
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