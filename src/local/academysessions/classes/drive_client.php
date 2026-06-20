<?php
namespace local_academysessions;

defined('MOODLE_INTERNAL') || die();

class drive_client {

    private $issuer;

    public function __construct() {
        $issuerid = get_config('googlemeet', 'issuerid');
        if ($issuerid) {
            $this->issuer = \core\oauth2\api::get_issuer($issuerid);
        }
    }

    private function get_system_client() {
        if (!$this->issuer) {
            throw new \Exception('Google OAuth2 issuer not configured');
        }

        $returnurl = new \moodle_url('/local/academysessions/api.php');
        $scopes = 'https://www.googleapis.com/auth/drive.readonly';
        $client = \core\oauth2\api::get_system_oauth_client($this->issuer);

        if (!$client) {
            throw new \Exception('Cannot get system OAuth client — ensure system account is connected in Site Admin > Server > OAuth 2 services');
        }

        return $client;
    }

    private function get_user_client($userid) {
        global $DB;

        if (!$this->issuer) {
            throw new \Exception('Google OAuth2 issuer not configured');
        }

        $refreshtoken = $DB->get_record('oauth2_refresh_token', array(
            'userid' => $userid,
            'issuerid' => $this->issuer->get('id')
        ));

        if (!$refreshtoken) {
            return null;
        }

        $returnurl = new \moodle_url('/local/academysessions/api.php');
        $scopes = 'https://www.googleapis.com/auth/drive.readonly';
        $client = \core\oauth2\api::get_user_oauth_client($this->issuer, $returnurl, $scopes, true);

        if (!$client || !$client->is_logged_in()) {
            return null;
        }

        return $client;
    }

    public function search_meet_recordings($client, $meetcode = '', $aftertime = 0) {
        $query = "mimeType='video/mp4' and trashed=false";

        if (!empty($meetcode)) {
            $query .= " and (name contains '" . addslashes($meetcode) . "')";
        } else {
            $query .= " and (name contains 'Meet Recording' or name contains 'meet_recording')";
        }

        if ($aftertime > 0) {
            $isodate = gmdate('Y-m-d\TH:i:s', $aftertime);
            $query .= " and createdTime > '" . $isodate . "'";
        }

        $params = array(
            'q' => $query,
            'fields' => 'files(id,name,mimeType,size,createdTime,webViewLink)',
            'orderBy' => 'createdTime desc',
            'pageSize' => 10
        );

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);
        $response = $client->get($url);

        if (!$response) {
            return array();
        }

        $data = json_decode($response);
        return isset($data->files) ? $data->files : array();
    }

    public function download_file($client, $fileid, $destpath) {
        $url = 'https://www.googleapis.com/drive/v3/files/' . $fileid . '?alt=media';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $client->get_accesstoken()->token
        ));

        $fp = fopen($destpath, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);

        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpcode >= 400) {
            @unlink($destpath);
            throw new \Exception("Drive download failed: HTTP $httpcode");
        }

        return filesize($destpath);
    }

    public function has_user_token($userid) {
        global $DB;
        if (!$this->issuer) {
            return false;
        }
        return $DB->record_exists('oauth2_refresh_token', array(
            'userid' => $userid,
            'issuerid' => $this->issuer->get('id')
        ));
    }
}
