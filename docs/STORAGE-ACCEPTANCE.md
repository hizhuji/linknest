# Storage Acceptance Checklist

Run this checklist in a non-production environment for every configured storage backend: local disk, Aliyun OSS, Tencent COS, Huawei OBS, Qiniu, Upyun, and SAE where applicable.

1. Upload a small text file and verify the generated download link.
2. Upload an image, audio file, and video file and verify each preview route.
3. Upload a file larger than 8 MB and verify multipart upload and MD5 validation.
4. Download with an HTTP range request and verify resume support.
5. Delete one uploaded object in the admin console and verify both database metadata and remote object deletion.
6. Test a filename containing Chinese characters, spaces, and quotes.
7. Confirm the provider certificate is valid; this maintenance version no longer disables TLS verification for application-owned HTTP calls.

Record the PHP version, provider region, SDK error output, and test date with each release validation.
