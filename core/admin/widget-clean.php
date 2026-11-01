<?php
/**
 * Ecod Theme · 模块参数清洗（widget.php 与页面构建器共用）
 *
 * 这三份函数的原籍在 widget.php（模块管理台入口）里，那里是
 * 无守卫定义；本文件提供 function_exists 守卫的等价实现，供
 * core/admin/builder-doc.php 在前台 / 独立入口里复用同一套口径。
 * 两边逻辑保持一致：修改清洗规则时两处都要改（或未来收编进本文件）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-31
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ecod_wgt_clean_params')) {
    /**
     * 参数清洗：只保留 schema 里声明过的键，按类型收口
     *
     * @param array $def 模块声明
     * @param array $in  前端提交的参数
     */
    function ecod_wgt_clean_params(array $def, array $in): array
    {
        $out = [];

        foreach (($def['params'] ?? []) as $key => $param) {
            if (!array_key_exists($key, $in)) {
                continue;
            }
            $value = $in[$key];
            $type = (string) ($param['type'] ?? 'text');

            switch ($type) {
                case 'number':
                    if ($value === '' || $value === null) {
                        $out[$key] = '';
                        break;
                    }
                    $num = (int) $value;
                    if (isset($param['min']) && $num < (int) $param['min']) {
                        $num = (int) $param['min'];
                    }
                    if (isset($param['max']) && $num > (int) $param['max']) {
                        $num = (int) $param['max'];
                    }
                    $out[$key] = $num;
                    break;

                case 'switch':
                    $out[$key] = !empty($value) ? 1 : 0;
                    break;

                case 'select':
                    $options = array_map('strval', array_keys((array) ($param['options'] ?? [])));
                    $pick = (string) $value;
                    $out[$key] = in_array($pick, $options, true) ? $pick : '';
                    break;

                case 'slides':
                    $out[$key] = ecod_wgt_clean_slides($value);
                    break;

                case 'term':
                    $pick = trim((string) $value);
                    $out[$key] = preg_match('/^\d+$/', $pick) ? $pick : '';
                    break;

                case 'terms':
                    $picks = preg_split('/[\s,，]+/', (string) $value) ?: [];
                    $picks = array_values(array_unique(array_filter($picks, static function ($one) {
                        return preg_match('/^\d+$/', (string) $one) === 1;
                    })));
                    $out[$key] = implode(',', $picks);
                    break;

                case 'checks':
                    /* 逗号分隔 key 串：只保留声明 options 里的 key，按声明顺序输出 */
                    $picked = [];
                    foreach (preg_split('/[\s,，]+/', (string) $value) ?: [] as $one) {
                        $one = trim((string) $one);
                        if ($one !== '' && array_key_exists($one, (array) ($param['options'] ?? []))) {
                            $picked[$one] = true;
                        }
                    }
                    $out[$key] = implode(',', array_keys(array_intersect_key((array) ($param['options'] ?? []), $picked)));
                    break;

                case 'image':
                    $url = trim(str_replace(["\r", "\n", "\t"], '', (string) $value));
                    if ($url !== '' && !preg_match('#^(https?://|/|\./|data:image/)#i', $url)) {
                        $url = '';
                    }
                    $out[$key] = mb_substr($url, 0, 500);
                    break;

                case 'textarea':
                    $out[$key] = mb_substr(trim((string) $value), 0, 20000);
                    break;

                case 'repeater':
                    $out[$key] = ecod_wgt_clean_repeater($value, (array) ($param['fields'] ?? []), (int) ($param['max'] ?? 20));
                    break;

                default:
                    $out[$key] = mb_substr(trim((string) $value), 0, 2000);
            }
        }

        return $out;
    }
}

if (!function_exists('ecod_wgt_clean_repeater')) {
    /**
     * repeater 参数：JSON 数组字符串清洗
     *
     * @param mixed $value JSON 字符串或数组
     */
    function ecod_wgt_clean_repeater($value, array $fields, int $max): string
    {
        if (is_string($value)) {
            $value = trim($value);
            $value = $value === '' ? [] : json_decode($value, true);
        }
        if (!is_array($value)) {
            return '';
        }

        $keys = [];
        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        if ($keys === []) {
            return '';
        }

        $out = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [];
            $filled = false;
            foreach ($keys as $key) {
                $one = mb_substr(trim((string) ($row[$key] ?? '')), 0, 500);
                if (str_contains($one, 'javascript:')) {
                    $one = '';
                }
                $item[$key] = $one;
                if ($one !== '') {
                    $filled = true;
                }
            }
            if ($filled) {
                $out[] = $item;
            }
            if (count($out) >= max(1, $max)) {
                break;
            }
        }

        return $out === [] ? '' : json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('ecod_wgt_clean_slides')) {
    /**
     * 幻灯片参数：JSON 字符串 ⇄ 数组，逐行清洗
     *
     * @param mixed $value JSON 字符串或数组
     */
    function ecod_wgt_clean_slides($value): string
    {
        if (is_string($value)) {
            $value = trim($value);
            $value = $value === '' ? [] : json_decode($value, true);
        }
        if (!is_array($value)) {
            return '';
        }

        $rows = [];
        foreach (array_slice($value, 0, 12) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'image' => mb_substr(trim((string) ($row['image'] ?? '')), 0, 500),
                'title' => mb_substr(trim((string) ($row['title'] ?? '')), 0, 120),
                'desc'  => mb_substr(trim((string) ($row['desc'] ?? '')), 0, 200),
                'link'  => mb_substr(trim((string) ($row['link'] ?? '')), 0, 500),
            ];
            if ($item['image'] === '' && $item['title'] === '') {
                continue;
            }
            $rows[] = $item;
        }

        return $rows ? (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    }
}

if (!function_exists('ecod_wgt_clean_rows')) {
    /**
     * 一整组实例的清洗：类型必须存在、uid 唯一（与 widget.php 同口径）
     *
     * @return array<int,array> 可写入的实例行
     */
    function ecod_wgt_clean_rows(string $area, array $rows): array
    {
        $defs = ecod_block_defs();
        $out = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            if ($type === '' || !isset($defs[$type])) {
                continue;
            }

            $uid = (string) ($row['uid'] ?? '');
            if (!preg_match('/^[a-z0-9-]{1,40}$/', $uid) || isset($seen[$uid])) {
                $uid = $type . '-' . (count($out) + 1);
            }
            if (isset($seen[$uid])) {
                continue;
            }

            $seen[$uid] = true;
            $out[] = [
                'uid'    => $uid,
                'type'   => $type,
                'on'     => !empty($row['on']) ? 1 : 0,
                'params' => ecod_wgt_clean_params($defs[$type], is_array($row['params'] ?? null) ? $row['params'] : []),
            ];

            if (count($out) >= ECOD_WIDGET_LIMIT) {
                break;
            }
        }

        return $out;
    }
}
