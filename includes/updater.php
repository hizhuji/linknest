<?php

if(!defined('PAN_UPDATE_MANIFEST_URL')){
	define('PAN_UPDATE_MANIFEST_URL', 'https://raw.githubusercontent.com/hizhuji/pan/main/update.json');
}

function pan_update_allowed_url($url, $hosts) {
	$parts = parse_url($url);
	if(!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
	if(strtolower($parts['scheme']) !== 'https') return false;
	return in_array(strtolower($parts['host']), $hosts, true);
}

function pan_update_fetch_text($url, $maxBytes = 1048576) {
	if(!function_exists('curl_init')) throw new RuntimeException('服务器未启用 cURL 扩展');
	if(!pan_update_allowed_url($url, ['raw.githubusercontent.com'])) throw new RuntimeException('更新清单地址不受信任');
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Pan-Updater/'.VERSION);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	$content = curl_exec($ch);
	$status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
	$error = curl_error($ch);
	curl_close($ch);
	if($content === false || $status !== 200) throw new RuntimeException('获取更新信息失败'.($error ? '：'.$error : ''));
	if(strlen($content) > $maxBytes) throw new RuntimeException('更新清单大小异常');
	return $content;
}

function pan_update_validate_manifest($manifest) {
	if(!is_array($manifest)) throw new RuntimeException('更新清单格式不正确');
	$required = ['version', 'version_name', 'package_url', 'sha256', 'released_at', 'changelog'];
	foreach($required as $key){
		if(!array_key_exists($key, $manifest)) throw new RuntimeException('更新清单缺少字段：'.$key);
	}
	if(!preg_match('/^[0-9]{4,10}$/', (string)$manifest['version'])) throw new RuntimeException('更新版本号不正确');
	if(!is_string($manifest['version_name']) || $manifest['version_name'] === '') throw new RuntimeException('更新版本名称不正确');
	if(!pan_update_allowed_url($manifest['package_url'], ['codeload.github.com'])) throw new RuntimeException('更新包地址不受信任');
	if(!preg_match('/^[a-f0-9]{64}$/i', (string)$manifest['sha256'])) throw new RuntimeException('更新包校验值不正确');
	if(!is_array($manifest['changelog'])) throw new RuntimeException('更新内容格式不正确');
	$manifest['version'] = intval($manifest['version']);
	$manifest['min_version'] = isset($manifest['min_version']) ? intval($manifest['min_version']) : 0;
	$manifest['database_version'] = isset($manifest['database_version']) ? intval($manifest['database_version']) : 0;
	$manifest['sha256'] = strtolower($manifest['sha256']);
	$manifest['changelog'] = array_values(array_filter(array_map('strval', $manifest['changelog']), 'strlen'));
	return $manifest;
}

function pan_update_fetch_manifest() {
	$data = json_decode(pan_update_fetch_text(PAN_UPDATE_MANIFEST_URL), true);
	if(json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('更新清单无法解析');
	return pan_update_validate_manifest($data);
}

function pan_update_download($url, $destination, $maxBytes = 209715200) {
	if(!function_exists('curl_init')) throw new RuntimeException('服务器未启用 cURL 扩展');
	if(!pan_update_allowed_url($url, ['codeload.github.com'])) throw new RuntimeException('更新包地址不受信任');
	$fp = fopen($destination, 'wb');
	if(!$fp) throw new RuntimeException('无法创建更新包临时文件');
	$received = 0;
	$tooLarge = false;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_TIMEOUT, 180);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Pan-Updater/'.VERSION);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($fp, $maxBytes, &$received, &$tooLarge) {
		$length = strlen($data);
		$received += $length;
		if($received > $maxBytes){
			$tooLarge = true;
			return 0;
		}
		return fwrite($fp, $data);
	});
	$result = curl_exec($ch);
	$status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
	$error = curl_error($ch);
	curl_close($ch);
	fclose($fp);
	if($tooLarge){
		@unlink($destination);
		throw new RuntimeException('更新包超过允许大小');
	}
	if($result === false || $status !== 200){
		@unlink($destination);
		throw new RuntimeException('下载更新包失败'.($error ? '：'.$error : ''));
	}
	return $received;
}

function pan_update_safe_archive($zip) {
	for($i = 0; $i < $zip->numFiles; $i++){
		$name = str_replace('\\', '/', $zip->getNameIndex($i));
		if($name === '' || substr($name, 0, 1) === '/' || preg_match('#(^|/)\.\.(/|$)#', $name)) return false;
	}
	return true;
}

function pan_update_remove_tree($path) {
	if(!file_exists($path)) return;
	if(is_file($path) || is_link($path)){
		@unlink($path);
		return;
	}
	$items = scandir($path);
	if($items){
		foreach($items as $item){
			if($item === '.' || $item === '..') continue;
			pan_update_remove_tree($path.DIRECTORY_SEPARATOR.$item);
		}
	}
	@rmdir($path);
}

function pan_update_find_package_root($extractDir) {
	if(is_file($extractDir.'/includes/common.php')) return $extractDir;
	$dirs = glob($extractDir.'/*', GLOB_ONLYDIR);
	if($dirs){
		foreach($dirs as $dir){
			if(is_file($dir.'/includes/common.php')) return $dir;
		}
	}
	throw new RuntimeException('更新包目录结构不正确');
}

function pan_update_package_version($packageRoot) {
	$content = file_get_contents($packageRoot.'/includes/common.php');
	if($content === false || !preg_match("/define\\('VERSION',\\s*'([0-9]+)'\\)/", $content, $matches)){
		throw new RuntimeException('无法识别更新包版本');
	}
	return intval($matches[1]);
}

function pan_update_preserve_path($relativePath) {
	$relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
	if($relativePath === 'config.php' || $relativePath === 'install/install.lock') return true;
	$prefixes = ['file/', 'install/backups/', 'install/update-temp/', '.git/'];
	foreach($prefixes as $prefix){
		if(strpos($relativePath, $prefix) === 0) return true;
	}
	return false;
}

function pan_update_package_files($packageRoot) {
	$files = [];
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packageRoot, FilesystemIterator::SKIP_DOTS));
	foreach($iterator as $file){
		if($file->isLink()) throw new RuntimeException('更新包不能包含符号链接');
		if(!$file->isFile()) continue;
		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($packageRoot) + 1));
		if(pan_update_preserve_path($relative)) continue;
		$files[$relative] = $file->getPathname();
	}
	if(!$files) throw new RuntimeException('更新包中没有可更新文件');
	return $files;
}

function pan_update_prepare_runtime_dir($path) {
	if(!is_dir($path) && !mkdir($path, 0755, true)) throw new RuntimeException('无法创建更新工作目录');
	if(!is_file($path.'/.htaccess')) @file_put_contents($path.'/.htaccess', "Require all denied\nDeny from all\n");
	if(!is_file($path.'/index.html')) @file_put_contents($path.'/index.html', '');
}

function pan_update_create_backup($files, $backupFile) {
	$zip = new ZipArchive();
	if($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('无法创建更新前备份');
	foreach($files as $relative => $source){
		$existing = ROOT.str_replace('/', DIRECTORY_SEPARATOR, $relative);
		if(is_file($existing) && !$zip->addFile($existing, $relative)){
			$zip->close();
			@unlink($backupFile);
			throw new RuntimeException('备份文件失败：'.$relative);
		}
	}
	$zip->close();
	if(!is_file($backupFile)) throw new RuntimeException('更新前备份创建失败');
}

function pan_update_replace_files($files) {
	foreach($files as $relative => $source){
		$destination = ROOT.str_replace('/', DIRECTORY_SEPARATOR, $relative);
		$directory = dirname($destination);
		if(!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('无法创建目录：'.$relative);
		if(!is_writable($directory)) throw new RuntimeException('目录不可写：'.str_replace(ROOT, '', $directory));
	}
	foreach($files as $relative => $source){
		$destination = ROOT.str_replace('/', DIRECTORY_SEPARATOR, $relative);
		$tempFile = $destination.'.pan-update-new';
		@unlink($tempFile);
		if(!copy($source, $tempFile)) throw new RuntimeException('写入更新文件失败：'.$relative);
		if(!@rename($tempFile, $destination)){
			$oldFile = $destination.'.pan-update-old';
			@unlink($oldFile);
			if(is_file($destination) && !@rename($destination, $oldFile)){
				@unlink($tempFile);
				throw new RuntimeException('替换更新文件失败：'.$relative);
			}
			if(!@rename($tempFile, $destination)){
				if(is_file($oldFile)) @rename($oldFile, $destination);
				@unlink($tempFile);
				throw new RuntimeException('替换更新文件失败：'.$relative);
			}
			@unlink($oldFile);
		}
	}
}

function pan_update_install($manifest) {
	if(!class_exists('ZipArchive')) throw new RuntimeException('服务器未启用 ZipArchive 扩展');
	$currentVersion = intval(VERSION);
	if($manifest['version'] <= $currentVersion) throw new RuntimeException('当前已经是最新版本');
	if($manifest['min_version'] > 0 && $currentVersion < $manifest['min_version']) throw new RuntimeException('当前版本过旧，需要先手工升级到兼容版本');
	$runtimeRoot = ROOT.'install/update-temp';
	$backupRoot = ROOT.'install/backups';
	pan_update_prepare_runtime_dir($runtimeRoot);
	pan_update_prepare_runtime_dir($backupRoot);
	$lockHandle = fopen(ROOT.'install/update.lock', 'c');
	if(!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) throw new RuntimeException('已有更新任务正在执行');
	$workDir = $runtimeRoot.'/'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
	if(!mkdir($workDir, 0755, true)) throw new RuntimeException('无法创建本次更新目录');
	try{
		$packageFile = $workDir.'/package.zip';
		pan_update_download($manifest['package_url'], $packageFile);
		if(!hash_equals($manifest['sha256'], strtolower(hash_file('sha256', $packageFile)))) throw new RuntimeException('更新包完整性校验失败');
		$zip = new ZipArchive();
		if($zip->open($packageFile) !== true) throw new RuntimeException('无法打开更新包');
		if(!pan_update_safe_archive($zip)){
			$zip->close();
			throw new RuntimeException('更新包包含不安全路径');
		}
		$extractDir = $workDir.'/extract';
		if(!mkdir($extractDir, 0755, true) || !$zip->extractTo($extractDir)){
			$zip->close();
			throw new RuntimeException('更新包解压失败');
		}
		$zip->close();
		$packageRoot = pan_update_find_package_root($extractDir);
		if(pan_update_package_version($packageRoot) !== $manifest['version']) throw new RuntimeException('更新包版本与清单不一致');
		$files = pan_update_package_files($packageRoot);
		$backupFile = $backupRoot.'/pan-backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip';
		pan_update_create_backup($files, $backupFile);
		pan_update_replace_files($files);
		return [
			'backup' => basename($backupFile),
			'database_update' => $manifest['database_version'] > intval(DB_VERSION),
		];
	}finally{
		pan_update_remove_tree($workDir);
		flock($lockHandle, LOCK_UN);
		fclose($lockHandle);
	}
}

