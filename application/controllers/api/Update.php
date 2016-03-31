<?php

defined('BASEPATH') OR exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
require APPPATH . '/libraries/REST_Controller.php';

class Update extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
    }

    function get_last_version_post()
    {
        //get data
        $project_name = $this->post('username');
        $project_password = $this->post('password');

        $version_info = $this->_get_project_info($project_name, $project_password);

        //return last version
        $this->response(array('err' => 0, 'message' => 'success', 'last_version' => $version_info->last_version));
    }

    function change_last_version_post()
    {
        //get data
        $project_name = $this->post('username');
        $project_password = $this->post('password');
        $project_version = $this->post('version');
        $version_info = $this->_get_project_info($project_name, $project_password);

        //Check data
        if ($project_version == ''){
            $this->response(array('err' => -5, 'message' => 'not enough data'));
        }

        //file path
        $folder_path = $this->config->item('upload_path') . '/' . $project_name;
        $version_path = $folder_path . '/' . $this->config->item('version_file');

        //Check file of version
        if (!file_exists($folder_path.'/'.$project_version.'.zip')){
            $this->response(array('err' => -8, 'message' => 'file of version not exist'));
        }

        //assign version
        $version_info->last_version = $project_version;

        //save version
        file_put_contents($version_path, json_encode($version_info));
        $this->response(array('err' => 0, 'message' => 'success'));
    }

    function download_last_version_post()
    {
        //get data
        $project_name = $this->post('username');
        $project_password = $this->post('password');

        //get project info
        $version_info = $this->_get_project_info($project_name, $project_password);

        //file path of last version
        $full_path = $this->config->item('upload_path') . '/' . $project_name . '/' . $version_info->last_version . '.zip';

        if (!file_exists($full_path)) {
            $this->response(array('err' => -6, 'message' => 'file not exist', 'path' => $full_path));
        }

        //get file info
        $file_size = filesize($full_path);
        $path_parts = pathinfo($full_path);

        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        //assign file type by extension
        switch ($path_parts['extension']) {
            case "pdf":
                $type = "application/pdf";
                break;
            case "zip":
                $type = "application/zip";
                break;
            case "doc":
                $type = "application/vnd.ms-word";
                break;
            case "xls":
                $type = "application/vnd.ms-excel";
                break;
            case "ppt":
                $type = "application/vnd.ms-powerpoint";
                break;
            case "gif":
                $type = "image/gif";
                break;
            case "png":
                $type = "image/png";
                break;
            default:
                $type = "application/force-download";
        }

        //Clean
        $this->_ob_clean_all();

        //Init Header
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false);
        header("Content-Type: $type");
        header("Content-Disposition: attachment; filename=" . $version_info->last_version . '.zip' . ";");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: " . $file_size);
        readfile($full_path);
        exit();
    }

    function versions_post()
    {
        //get data
        $project_name = $this->post('username');
        $project_password = $this->post('password');
        $this->_get_project_info($project_name, $project_password);

        $folder_path = $this->config->item('upload_path') . '/' . $project_name;
        $files = scandir($folder_path);

        $file_infos = [];
        foreach ($files as $file) {
            $exp = explode('.', $file);
            if (count($exp) > 0 && strtolower($exp[count($exp) - 1]) == 'zip') {
                $file_infos[] = array(
                    'name' => join('.', array_slice($exp, 0, count($exp) - 1)),
                    'modified' => date("d-m-Y H:i:s", filemtime($folder_path . '/' . $file))
                );
            }
        }

        $this->response(array('error' => 0, 'message' => 'success', 'files' => $file_infos));
    }

    function upload_post()
    {
        //get data
        $project_name = $this->post('username');
        $project_password = $this->post('password');

        //Check data
        if ($project_name == '' || $project_password == '') {
            $this->response(array('err' => -5, 'message' => 'not enough data'));
        }

        //Check file is exist
        if (!isset($_FILES['file'])) {
            $this->response(array('error' => -4, 'message' => 'no file field'));
        }
        $file = $_FILES['file'];

        //file path
        $folder_path = $this->config->item('upload_path') . '/' . $project_name;
        $version_path = $folder_path . '/' . $this->config->item('version_file');

        //create folder if not exist
        if (!file_exists($folder_path)) {
            mkdir($folder_path, 0750, true);
            touch($folder_path . '/index.html');
        }

        //get version
        $exp = explode('.', $file['name']);
        $version = join('.', array_slice($exp, 0, count($exp) - 1));

        //Check version file
        if (!file_exists($version_path)) {
            $version_info = array(
                'password' => $project_password,
                'last_version' => $version
            );
            file_put_contents($version_path, json_encode($version_info));
        } else {
            $version_info = json_decode(file_get_contents($version_path));

            if ($project_password != $version_info->password) {
                $this->response(array('err' => -1, 'message' => 'wrong password', 'info' => $version_info));
            }
        }

        //Config upload
        $config['upload_path'] = $folder_path;
        $config['allowed_types'] = $this->config->item('upload_allowed_types');
        $config['max_size'] = $this->config->item('upload_max_size');
        $config['file_name'] = $file['name'];
        $config['overwrite'] = TRUE;
        $config['mod_mime_fix'] = FALSE;

        //Load library
        $this->load->library('upload');
        $this->upload->initialize($config);

        //Do upload
        if (!$this->upload->do_upload('file'))
        {
            $this->response(array('error' => -7, 'message' => $this->upload->display_errors()));
        }
        else
        {
            $this->response(array('error' => 0, 'message' => 'upload success'));
        }
    }

    private function _get_project_info($project_name, $project_password)
    {
        //Check data
        if ($project_name == '' || $project_password == '') {
            $this->response(array('err' => -5, 'message' => 'not enough data'));
        }

        //file path
        $folder_path = $this->config->item('upload_path') . '/' . $project_name;
        $version_path = $folder_path . '/' . $this->config->item('version_file');

        //Check folder
        if (!file_exists($folder_path)) {
            $this->response(array('err' => -4, 'message' => 'project not exist'));
        }

        //Check version file
        if (!file_exists($version_path)) {
            $this->response(array('err' => -3, 'message' => 'project have version'));
        }

        //Check password
        $project_info = [];
        try {
            $project_info = json_decode(file_get_contents($version_path));
        } catch (Exception $e) {
            $this->response(array('err' => -2, 'message' => 'version wrong format'));
        }

        if ($project_password != $project_info->password) {
            $this->response(array('err' => -1, 'message' => 'wrong password'));
        }

        return $project_info;
    }

    private function _ob_clean_all()
    {
        $ob_active = ob_get_length() !== FALSE;
        while ($ob_active) {
            ob_end_clean();
            $ob_active = ob_get_length() !== FALSE;
        }
        return FALSE;
    }
}
