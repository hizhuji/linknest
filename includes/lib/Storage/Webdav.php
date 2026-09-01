<?php
namespace lib\Storage;
use \lib\IStorage;

class Webdav implements IStorage {
	private $config;
	private $errmsg;
	private $baseUrl;

	public function __construct($config) {
		$this->config = $config;
		$endpoint = rtrim(trim($config['endpoint']), '/');
		$root = trim($config['root'], '/');
		$this->baseUrl = $endpoint . ($root !== '' ? '/' . $this->encodePath($root) : '') . '/file/';
	}

	public function getClient(){ return null; }
	public function errmsg(){ return $this->errmsg; }

	private function encodePath($path) {
		$segments = explode('/', str_replace('\\', '/', trim($path, '/')));
		return implode('/', array_map('rawurlencode', $segments));
	}

	private function url($name) { return $this->baseUrl . $this->encodePath($name); }

	private function request($method, $url, $options = []) {
		if(!function_exists('curl_init')){
			$this->errmsg = '服务器未启用 cURL 扩展';
			return false;
		}
		if(!preg_match('#^https?://#i', $url)){
			$this->errmsg = 'WebDAV 地址必须以 http:// 或 https:// 开头';
			return false;
		}
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => isset($options['timeout']) ? $options['timeout'] : 60,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HEADER => !empty($options['header']),
		]);
		if($this->config['username'] !== '' || $this->config['password'] !== ''){
			curl_setopt($ch, CURLOPT_USERPWD, $this->config['username'].':'.$this->config['password']);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		}
		if(!empty($options['headers'])) curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
		if(isset($options['file'])){
			$handle = fopen($options['file'], 'rb');
			if(!$handle){ $this->errmsg = '无法读取待上传文件'; curl_close($ch); return false; }
			curl_setopt($ch, CURLOPT_UPLOAD, true);
			curl_setopt($ch, CURLOPT_INFILE, $handle);
			curl_setopt($ch, CURLOPT_INFILESIZE, filesize($options['file']));
		}
		if(isset($options['write'])){
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, $options['write']);
		}
		$body = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		if(isset($handle)) fclose($handle);
		curl_close($ch);
		if($body === false || $status < 200 || $status >= 300){
			$this->errmsg = $error !== '' ? $error : 'WebDAV 请求失败（HTTP '.$status.'）';
			return false;
		}
		return ['status'=>$status, 'body'=>$body];
	}

	private function ensureCollection() {
		$endpoint = rtrim(trim($this->config['endpoint']), '/');
		$parts = array_filter(explode('/', trim($this->config['root'], '/')));
		$parts[] = 'file';
		$url = $endpoint;
		foreach($parts as $part){
			$url .= '/'.rawurlencode($part);
			$result = $this->request('MKCOL', $url, ['timeout'=>20]);
			if($result === false && strpos($this->errmsg, '405') === false && strpos($this->errmsg, '301') === false) return false;
		}
		return true;
	}

	public function exists($name) {
		$result = $this->request('PROPFIND', $this->url($name), ['headers'=>['Depth: 0'], 'timeout'=>20]);
		return $result !== false;
	}

	public function get($name) {
		$result = $this->request('GET', $this->url($name));
		return $result === false ? false : $result['body'];
	}

	public function downfile($name, $range = false) {
		$headers = [];
		if(is_array($range)) $headers[] = 'Range: bytes='.$range[0].'-'.$range[1];
		$result = $this->request('GET', $this->url($name), [
			'headers'=>$headers,
			'timeout'=>0,
			'write'=>function($ch, $data){ echo $data; flush(); return strlen($data); },
		]);
		return $result !== false;
	}

	public function upload($name, $tmpfile, $content_type = null) {
		if(!$this->ensureCollection()) return false;
		$headers = $content_type ? ['Content-Type: '.$content_type] : [];
		return $this->request('PUT', $this->url($name), ['file'=>$tmpfile, 'headers'=>$headers, 'timeout'=>0]) !== false;
	}

	public function savefile($name, $tmpfile, $content_type = null) { return $this->upload($name, $tmpfile, $content_type); }

	public function getinfo($name) {
		$result = $this->request('HEAD', $this->url($name), ['header'=>true, 'timeout'=>20]);
		if($result === false) return false;
		$headers = $result['body'];
		$length = preg_match('/^Content-Length:\s*(\d+)/mi', $headers, $match) ? (int)$match[1] : 0;
		$type = preg_match('/^Content-Type:\s*([^\r\n]+)/mi', $headers, $match) ? trim($match[1]) : null;
		return ['length'=>$length, 'content_type'=>$type];
	}

	public function delete($name) { return $this->request('DELETE', $this->url($name), ['timeout'=>30]) !== false; }
	public function getUploadParam($name, $filename, $max_file_size = 0){ return false; }

	public function getDownUrl($name, $filename = null, $content_type = null){
		if(empty($this->config['publicUrl'])){
			$this->errmsg = '未配置 WebDAV 公开下载地址';
			return false;
		}
		return rtrim($this->config['publicUrl'], '/').'/file/'.$this->encodePath($name);
	}
}
