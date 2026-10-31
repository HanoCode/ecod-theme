<?php
/**
 * 设置页渲染冒烟 harness（仅本地开发用，不进生产）
 *
 * stub 掉 Typecho 表单类与主题函数，静态渲染 core/admin/config.php 的完整 DOM，
 * 供浏览器打 open file:// 截图验证布局与交互。用法：
 *   php temp/admin-harness.php > temp/admin-harness.html
 */

namespace {
    define('__TYPECHO_ROOT_DIR__', __DIR__);
}

namespace Widget {
    class Options
    {
        public $siteUrl = 'https://demo.example.com';
        public $themeUrl = 'https://demo.example.com/usr/themes/ecod-theme';

        public static function alloc(): self
        {
            static $i;
            return $i ?: $i = new self();
        }
    }

    class Security
    {
        public function getIndex(string $a): string
        {
            return $a . '?_=stub';
        }

        public function getToken(string $suffix = null): string
        {
            return 'stub-token';
        }

        public static function alloc(): self
        {
            static $i;
            return $i ?: $i = new self();
        }
    }
}

namespace Utils {
    class Helper
    {
        public static function options()
        {
            return \Widget\Options::alloc();
        }
    }
}

namespace Typecho\Widget\Helper\Form\Element {
    class Input
    {
        private $attrs = [];

        public function setAttribute($k, $v)
        {
            $this->attrs[$k] = (string) $v;
            return $this;
        }

        public function getAttrs(): array
        {
            return $this->attrs;
        }
    }

    abstract class Element
    {
        public $input;
        public $name;
        public $options;
        public $value;
        public $label;
        public $description;
        private $attrs = [];

        public function __construct($name, $options, $value, $label = '', $desc = '')
        {
            $this->name = $name;
            $this->options = $options ?: [];
            $this->value = $value;
            $this->label = $label;
            $this->description = $desc;
            $this->input = new Input();
        }

        public function setAttribute($k, $v = null)
        {
            $this->attrs[$k] = (string) $v;
            return $this;
        }

        public function getAttr($k)
        {
            return $this->attrs[$k] ?? null;
        }

        public function pane(): string
        {
            return (string) ($this->attrs['data-ecod-pane'] ?? '');
        }

        abstract public function kind(): string;
    }

    class Text extends Element
    {
        public function kind(): string
        {
            return 'text';
        }
    }
    class Textarea extends Element
    {
        public function kind(): string
        {
            return 'textarea';
        }
    }
    class Select extends Element
    {
        public function kind(): string
        {
            return 'select';
        }
    }
    class Number extends Element
    {
        public function kind(): string
        {
            return 'number';
        }
    }
    class Hidden extends Element
    {
        public function __construct($name, $options, $value)
        {
            parent::__construct($name, null, $value, '', '');
        }

        public function kind(): string
        {
            return 'hidden';
        }
    }
}

namespace {
    /* ---------------- 主题函数 stub ---------------- */
    function ecod_option(string $k, $d = null)
    {
        return $d;
    }

    function ecod_asset(string $p): string
    {
        return '../' . $p;
    }

    function ecod_icon(string $name, int $size = 16, string $cls = ''): string
    {
        return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg>';
    }

    function ecod_icon_names(): array
    {
        return ['home', 'search', 'layers', 'comment'];
    }

    function ecod_nav_sources(): array
    {
        return ['page' => [], 'cat' => [], 'tag' => []];
    }

    function ecod_nav_sets(): array
    {
        return ['sets' => [], 'active' => ''];
    }

    function ecod_nav_home_url(): string
    {
        return '/';
    }

    function ecod_topics(bool $flat = true): array
    {
        return [];
    }

    function ecod_topic_index(): array
    {
        return [];
    }

    function ecod_topics_page(): array
    {
        return ['slug' => 'topics', 'title' => '专题'];
    }

    define('ECOD_TOPICS_MAX', 30);

    function ecod_snapshot_list(): array
    {
        return [];
    }

    function ecod_pages_state(): array
    {
        return [];
    }

    function ecod_tool_token(): string
    {
        return 'stub-token';
    }

    /* ---------------- 表单 stub：按 Typecho 的 ul.typecho-option 结构渲染 ---------------- */
    class EcodStubForm
    {
        private $els = [];

        public function addInput($el)
        {
            $this->els[] = $el;
        }

        private function attrs(array $attrs): string
        {
            $out = '';
            foreach ($attrs as $k => $v) {
                $out .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
            }
            return $out;
        }

        public function render(): string
        {
            $html = '';
            foreach ($this->els as $el) {
                $pane = htmlspecialchars($el->pane(), ENT_QUOTES, 'UTF-8');
                $kind = $el->kind();
                $inAttrs = $el->input->getAttrs();
                $name = htmlspecialchars($el->name, ENT_QUOTES, 'UTF-8');
                $value = htmlspecialchars((string) $el->value, ENT_QUOTES, 'UTF-8');

                if ($kind === 'hidden') {
                    $html .= '<ul class="typecho-option" data-ecod-pane="' . $pane . '"><li><input type="hidden" name="' . $name . '" value="' . $value . '"' . $this->attrs($inAttrs) . '></li></ul>';
                    continue;
                }

                $label = htmlspecialchars($el->label, ENT_QUOTES, 'UTF-8');
                $desc = htmlspecialchars($el->description, ENT_QUOTES, 'UTF-8');

                if ($kind === 'select') {
                    $opts = '';
                    foreach ($el->options as $ov => $ol) {
                        $opts .= '<option value="' . htmlspecialchars((string) $ov, ENT_QUOTES, 'UTF-8') . '"' . ((string) $ov === (string) $el->value ? ' selected' : '') . '>' . htmlspecialchars((string) $ol, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $control = '<select name="' . $name . '"' . $this->attrs($inAttrs) . '>' . $opts . '</select>';
                } elseif ($kind === 'textarea') {
                    $control = '<textarea name="' . $name . '"' . $this->attrs($inAttrs) . '>' . $value . '</textarea>';
                } else {
                    $control = '<input type="' . $kind . '" name="' . $name . '" value="' . $value . '"' . $this->attrs($inAttrs) . '>';
                }

                $html .= '<ul class="typecho-option" data-ecod-pane="' . $pane . '"><li>'
                    . '<label class="typecho-label">' . $label . '</label>'
                    . $control
                    . '<p class="description">' . $desc . '</p>'
                    . '</li></ul>';
            }

            return $html . '<div class="typecho-option-submit"><button type="submit">保存设置</button></div>';
        }
    }

    $form = new EcodStubForm();

    ob_start();
    require __DIR__ . '/../core/admin/config.php';
    $body = ob_get_clean();

    ?><!DOCTYPE html>
<html lang="zh-Hans">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>设置外观 · harness</title>
<style>body{margin:0;background:#eef0f4;font-family:-apple-system,"PingFang SC",sans-serif;color:#1c2130}.typecho-page-main{max-width:1200px;margin:0 auto;padding:24px}.typecho-option-submit button{padding:8px 18px;border:0;border-radius:8px;background:#4f6ef2;color:#fff;font-size:13px;cursor:pointer}</style>
</head>
<body>
<div class="typecho-page-main col-tb-8">
<h1>设置外观</h1>
<?php echo $body; ?>
<form method="post">
<?php echo $form->render(); ?>
</form>
</div>
</body>
</html>
<?php
}
