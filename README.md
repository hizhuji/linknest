# LinkNest 外链云盘

LinkNest 是一个面向自建站点的文件外链、分享与网盘挂载程序。

本项目基于原作者开源项目 [`netcccyun/pan`](https://github.com/netcccyun/pan) 继续维护，遵循 MIT 许可证。原项目版权声明完整保留；LinkNest 是社区维护的新名称，与原作者不存在官方隶属或背书关系。

支持本地存储、WebDAV、阿里云 OSS、腾讯云 COS、华为云 OBS、又拍云、七牛云等存储后端，提供文件外链、图片/音视频预览、分块上传和管理后台。WebDAV 可直接连接 AList、Nextcloud 等服务，也可通过 AList 挂载 Google Drive、OneDrive、阿里云盘等网盘。

上传时可为分享链接设置有效期和最大访问次数；下载与在线预览统一计数，链接过期或达到上限后会自动停止访问。文件所有者可在详情页续期，管理员可在文件管理中调整策略。

后台“网站信息设置”支持配置新的站点外链地址。更换域名后，分享、下载、预览、播放器、API、二维码和登录回调会统一使用新地址；留空则继续自动识别当前域名。

后台“在线更新”可直接检查并安装 LinkNest 稳定版。更新包会先完成来源限制、SHA-256 校验和安全解压，再备份并覆盖程序文件；`config.php`、本地上传目录和安装锁不会被替换。服务器需启用 cURL 与 ZipArchive，并允许 PHP 写入程序目录。

用户登录支持原有 QQ、微信聚合登录，以及直连 Google OAuth 2.0 和 Sign in with Apple。Google/Apple 回调地址会在后台“用户登录设置”中按当前站点域名自动生成。

## 环境要求

- PHP 7.4 至 8.3
- MySQL 5.7+ 或兼容的 MariaDB
- PHP 扩展：`pdo_mysql`、`curl`

## 安装

1. 部署代码后访问 `/install/`，按页面引导填写数据库信息。
2. 安装完成页会显示一次随机管理员密码，请立即妥善保存。
3. `config.php` 是运行时配置，仓库只提供 `config.php.example`，不得提交实际配置或存储凭据。

## 从旧版升级

升级前备份数据库、`config.php` 和本地上传目录；更新代码后访问 `/install/update.php` 完成数据库升级。详细步骤见 [docs/UPGRADE.md](docs/UPGRADE.md)。

升级到数据库版本 `1002` 后，管理员和用户需要重新登录。管理员使用原密码首次登录时，系统会自动将旧明文密码升级为安全哈希。

## API 安全

上传 API 默认关闭。启用后可在后台设置来源域名白名单和 API 密钥。建议先为调用方配置 `X-Api-Key`，再开启 API 密钥校验。

上传 API 支持可选参数 `expire_days`（0 至 3650，0 表示永久）和 `max_downloads`（0 至 1000000，0 表示不限次数）。

## 项目来源与维护

- 当前仓库：`https://github.com/hizhuji/linknest`
- 原始源码：`https://github.com/netcccyun/pan`
- LinkNest 使用 `main` 作为稳定分支，版本通过 Git tag 发布。
- 贡献流程见 [CONTRIBUTING.md](CONTRIBUTING.md)，安全报告见 [SECURITY.md](SECURITY.md)。
- 各存储后端发布前验收见 [docs/STORAGE-ACCEPTANCE.md](docs/STORAGE-ACCEPTANCE.md)。
