# WebDAV 挂载网盘

在后台打开“系统设置” -> “存储类型设置”，展开“WebDAV 挂载网盘”并填写：

1. WebDAV 地址：服务提供的 WebDAV 根地址，例如 AList 的 `https://example.com/dav`。
2. 用户名与密码：建议使用独立账号或应用密码。
3. 挂载目录：可填写 `pan-storage`，程序会自动在其下创建 `file` 目录。
4. 公开下载地址：只有文件无需认证即可访问时才填写；私有网盘请留空。
5. 保存后，在页面顶部把存储类型切换为“WebDAV 挂载网盘”。

WebDAV 不支持浏览器直传，上传会经过网站服务器。私有 WebDAV 下载也会经过网站服务器，因此需要关注服务器带宽和 PHP 超时限制。

Google Drive、OneDrive、阿里云盘等未直接提供标准 WebDAV 地址的服务，可以先在 AList 中挂载，再将 AList 的 WebDAV 地址接入本站。
