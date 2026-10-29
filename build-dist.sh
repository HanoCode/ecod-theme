#!/usr/bin/env bash
#
# Ecod Theme · 发布打包
#
# 生成可直接上传安装的发行 zip，排除开发产物：
#   temp/、探针脚本、备份目录、设计稿 / 审计文档、agent 记忆、.git
#
# 用法：./build-dist.sh [输出目录]   （默认输出到主题目录）
#
set -euo pipefail
cd "$(dirname "$0")"

name="ecod-theme"
out="${1:-.}"
stamp="$(date +%Y%m%d-%H%M%S)"
zip="$out/${name}-${stamp}.zip"

if ! command -v zip >/dev/null 2>&1; then
    echo "需要 zip 命令（macOS 自带，Linux 可 yum/apt install zip）" >&2
    exit 1
fi

rm -f "$zip"
# 注意：zip 的 -x 模式作用于相对路径，必须逐条列出
zip -rq "$zip" . \
    -x ".git/*" \
    -x ".workbuddy/*" \
    -x "temp/*" \
    -x "__probe*" \
    -x "backup-*" \
    -x "docs/*" \
    -x "*.md" \
    -x "MIRAI-REFERENCE.html" \
    -x "REFERENCE-TEARDOWN.html" \
    -x "design-proposal.html" \
    -x "build-dist.sh" \
    -x "*.tar.gz"

echo "已生成：$zip"
echo "内容抽查（不应出现 temp / probe / 设计稿）："
unzip -l "$zip" | grep -Ec "temp/|__probe|design-proposal|REFERENCE" && {
    echo "发现不该打包的文件，检查排除规则！" >&2
    exit 1
} || true
unzip -l "$zip" | tail -1
