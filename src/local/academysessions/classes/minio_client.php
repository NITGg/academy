<?php
namespace local_academysessions;

defined('MOODLE_INTERNAL') || die();

class minio_client {

    private $endpoint;
    private $accesskey;
    private $secretkey;
    private $bucket;

    public function __construct() {
        $this->endpoint = get_config('local_academysessions', 'minio_endpoint') ?: 'http://minio:9000';
        $this->accesskey = get_config('local_academysessions', 'minio_access_key') ?: 'minioadmin';
        $this->secretkey = get_config('local_academysessions', 'minio_secret_key') ?: 'minioadmin123';
        $this->bucket = get_config('local_academysessions', 'minio_bucket') ?: 'academy-recordings';
    }

    public function ensure_bucket() {
        $url = $this->endpoint . '/' . $this->bucket;
        $date = gmdate('D, d M Y H:i:s T');
        $resource = '/' . $this->bucket . '/';

        $tosign = "PUT\n\n\n{$date}\n{$resource}";
        $sig = base64_encode(hash_hmac('sha1', $tosign, $this->secretkey, true));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $date,
            'Authorization: AWS ' . $this->accesskey . ':' . $sig
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_exec($ch);
        curl_close($ch);
    }

    public function list_recordings() {
        $url = $this->endpoint . '/' . $this->bucket . '?prefix=recordings/&list-type=2';
        $date = gmdate('D, d M Y H:i:s T');
        $resource = '/' . $this->bucket;  // no trailing slash — must match URL path exactly

        $tosign = "GET\n\n\n{$date}\n{$resource}";
        $sig = base64_encode(hash_hmac('sha1', $tosign, $this->secretkey, true));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $date,
            'Authorization: AWS ' . $this->accesskey . ':' . $sig
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        if (empty($response)) {
            return array();
        }

        $xml = @simplexml_load_string($response);
        if (!$xml) {
            return array();
        }

        $files = array();
        if (isset($xml->Contents)) {
            foreach ($xml->Contents as $item) {
                $key = (string)$item->Key;
                if (substr($key, -4) === '.mp4') {
                    $files[] = array(
                        'key' => $key,
                        'size' => (int)$item->Size,
                        'modified' => (string)$item->LastModified
                    );
                }
            }
        }
        return $files;
    }

    public function download_file($key, $destpath) {
        $date = gmdate('D, d M Y H:i:s T');
        $resource = '/' . $this->bucket . '/' . $key;

        $tosign = "GET\n\n\n{$date}\n{$resource}";
        $sig = base64_encode(hash_hmac('sha1', $tosign, $this->secretkey, true));

        $url = $this->endpoint . '/' . $this->bucket . '/' . $key;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $date,
            'Authorization: AWS ' . $this->accesskey . ':' . $sig
        ));

        $fp = fopen($destpath, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);

        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpcode >= 400) {
            @unlink($destpath);
            throw new \Exception("MinIO download failed: HTTP $httpcode");
        }

        return filesize($destpath);
    }

    public function delete_file($key) {
        $date = gmdate('D, d M Y H:i:s T');
        $resource = '/' . $this->bucket . '/' . $key;

        $tosign = "DELETE\n\n\n{$date}\n{$resource}";
        $sig = base64_encode(hash_hmac('sha1', $tosign, $this->secretkey, true));

        $url = $this->endpoint . '/' . $this->bucket . '/' . $key;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $date,
            'Authorization: AWS ' . $this->accesskey . ':' . $sig
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_exec($ch);
        curl_close($ch);
    }

    public function is_configured() {
        return !empty($this->endpoint) && !empty($this->accesskey) && !empty($this->secretkey);
    }
}
