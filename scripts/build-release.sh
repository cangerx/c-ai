#!/bin/bash
# 打包发布脚本：清理 AWS SDK 只保留 S3，生成精简源码包
set -e
cd "$(dirname "$0")/.."

PROJECT_DIR="$(pwd)"
DIST_DIR="/tmp/cang-ai-dist"
OUTPUT="/tmp/cang-ai-release.tar.gz"

rm -rf "$DIST_DIR"

# 用 git archive 导出干净源码，再补上 vendor
git archive HEAD | tar -x -C "$(mkdir -p "$DIST_DIR" && echo "$DIST_DIR")"
cp -a vendor "$DIST_DIR/vendor"

# 删除不需要的
rm -rf "$DIST_DIR/tests" "$DIST_DIR/.plan.md"

# 清理 AWS SDK：只保留 S3 + 核心
AWS_SRC="$DIST_DIR/vendor/aws/aws-sdk-php/src"
if [ -d "$AWS_SRC" ]; then
  KEEP="S3 Arn Multipart Crypto EndpointV2 Token Identity Endpoint Exception Credentials Signature Retry ClientSideMonitoring Api"
  for dir in "$AWS_SRC"/*/; do
    [ ! -d "$dir" ] && continue
    dirname=$(basename "$dir")
    keep=false
    for k in $KEEP; do
      [ "$dirname" = "$k" ] && keep=true && break
    done
    [ "$keep" = false ] && rm -rf "$dir"
  done

  # data 目录只保留 s3 和 endpoints
  AWS_DATA="$AWS_SRC/data"
  if [ -d "$AWS_DATA" ]; then
    for dir in "$AWS_DATA"/*/; do
      [ ! -d "$dir" ] && continue
      dirname=$(basename "$dir")
      [ "$dirname" != "s3" ] && [ "$dirname" != "endpoints" ] && rm -rf "$dir"
    done
  fi
fi

# 清理 vendor 测试和文档
find "$DIST_DIR/vendor" -type d \( -name "tests" -o -name "Tests" -o -name "test" -o -name "docs" \) -exec rm -rf {} + 2>/dev/null || true
find "$DIST_DIR/vendor" \( -name "*.md" -o -name "CHANGELOG*" -o -name "UPGRADING*" \) -delete 2>/dev/null || true

# 打包
cd "$DIST_DIR"
tar -czf "$OUTPUT" .
SIZE=$(du -h "$OUTPUT" | cut -f1)
echo "✅ 打包完成: $OUTPUT ($SIZE)"
rm -rf "$DIST_DIR"
