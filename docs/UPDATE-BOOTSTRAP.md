# 在线更新 TLS 握手失败

如果 V6.3.0 在线更新显示 `ssl3_read_bytes:sslv3 alert handshake failure`，表示服务器可以读取 GitHub 更新清单，但无法从 `codeload.github.com` 下载源码包。

这是旧更新器的下载源兼容问题，不需要重新安装，也不要关闭 SSL 证书校验。

处理方法：

1. 从维护仓库 `main` 分支下载最新的 `includes/updater.php`。
2. 在网站文件管理器中覆盖网站的 `includes/updater.php`。
3. 回到后台“在线更新”，重新点击“检查更新”和“备份并立即更新”。

这个文件是一次性的更新引导补丁。升级到 V6.4.1 后，更新器会自动使用兼容下载源并保留 SHA-256 校验，后续无需再次手动覆盖。
