<?php
namespace local_academysessions;

defined('MOODLE_INTERNAL') || die();

class bunny_client {

    private $apikey;
    private $libraryid;
    private $cdnhostname;
    private $tokenkey;
    private $baseurl = 'https://video.bunnycdn.com';

    public function __construct() {
        $this->apikey = get_config('local_academysessions', 'bunny_api_key');
        $this->libraryid = get_config('local_academysessions', 'bunny_library_id');
        $this->cdnhostname = get_config('local_academysessions', 'bunny_cdn_hostname');
        $this->tokenkey = get_config('local_academysessions', 'bunny_token_key');
    }

    private function request($endpoint, $method = 'GET', $body = null) {
        $url = $this->baseurl . '/library/' . $this->libraryid . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'AccessKey: ' . $this->apikey,
            'Content-Type: application/json',
            'Accept: application/json'
        ));

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        } else if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } else if ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode >= 400) {
            throw new \Exception("Bunny API error: HTTP $httpcode - $response");
        }

        return json_decode($response);
    }

    public function create_video($title) {
        return $this->request('/videos', 'POST', array('title' => $title));
    }

    public function upload_video_from_file($videoid, $filepath) {
        $url = $this->baseurl . '/library/' . $this->libraryid . '/videos/' . $videoid;
        $filesize = filesize($filepath);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'AccessKey: ' . $this->apikey,
            'Content-Type: application/octet-stream'
        ));
        $fh = fopen($filepath, 'rb');
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, $filesize);
        curl_setopt($ch, CURLOPT_UPLOAD, true);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($httpcode >= 400) {
            throw new \Exception("Bunny upload error: HTTP $httpcode");
        }

        return json_decode($response);
    }

    public function get_video($videoid) {
        return $this->request('/videos/' . $videoid);
    }

    public function delete_video($videoid) {
        return $this->request('/videos/' . $videoid, 'DELETE');
    }

    public function get_embed_url($videoid, $expiresat = 0) {
        $base = 'https://iframe.mediadelivery.net/embed/' . $this->libraryid . '/' . $videoid;

        if (!empty($this->tokenkey) && $expiresat > 0) {
            $token = hash('sha256', $this->tokenkey . $videoid . $expiresat);
            return $base . '?token=' . $token . '&expires=' . $expiresat . '&preload=true';
        }

        return $base . '?preload=true';
    }

    public function get_playback_url($videoid) {
        return 'https://' . $this->cdnhostname . '/' . $videoid . '/playlist.m3u8';
    }

    public function get_thumbnail_url($videoid) {
        return 'https://' . $this->cdnhostname . '/' . $videoid . '/thumbnail.jpg';
    }

    public function map_status($bunnystatus) {
        switch ($bunnystatus) {
            case 0: case 7: return 'syncing';
            case 1: return 'syncing';
            case 2: case 3: return 'syncing';
            case 4: return 'ready';
            case 5: case 6: return 'failed';
            default: return 'syncing';
        }
    }

    public function is_configured() {
        return !empty($this->apikey) && !empty($this->libraryid) && !empty($this->cdnhostname);
    }
}
