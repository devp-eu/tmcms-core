<?php

namespace TMCms\Templates;

use TMCms\Config\Settings;
use TMCms\Files\Finder;
use TMCms\Files\MimeTypes;
use TMCms\Traits\singletonOnlyInstanceTrait;

defined('INC') or exit;

/**
 * Class PageHead
 * Generates HTML code for <head></head>
 */
class PageHead
{
    use singletonOnlyInstanceTrait;

    private
        $ssl = false,
        $doctype = '<!DOCTYPE HTML>',
        $title = '',
        $description = '',
        $keywords = '',
        $custom_strings = [],
        $meta = [],
        $css = [],
        $css_urls = [],
        $js_sequence = 0,
        $js_urls = [],
        $js = [],
        $rss = [],
        $favicon = [];
    private $html_tag_attributes = [];
    private $body_tag_attributes = '';
    private $apple_touch_icon_url = '';
    private $body_css_classes = [];
    private $replace_for_standard_html_tag = false;

    /**
     * @param string $attr_string
     * @return $this
     */
    public function addHtmlTagAttributes($attr_string)
    {
        $this->html_tag_attributes[] = $attr_string;

        return $this;
    }

    /**
     * @param string $class
     * @return $this
     */
    public function addClassToBody($class)
    {
        $this->body_css_classes[] = $class;

        return $this;
    }

    /**
     * @return array
     */
    public function getBodyCssClasses()
    {
        return $this->body_css_classes;
    }

    /**
     * @param string $attr_string
     * @return $this
     */
    public function setBodyTagAttributes($attr_string)
    {
        $this->body_tag_attributes = $attr_string;

        return $this;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setAppleTouchIcon($url)
    {
        $this->apple_touch_icon_url = $url;

        return $this;
    }

    /**
     * @param string $doctype
     * @param string $version
     * @param string $type
     * @return $this$this
     */
    public function setDoctype($doctype = 'html', $version = '5', $type = 'dtd')
    {
        $doc_types = [
            'html' => [
                '5' => ['dtd' => '<!DOCTYPE HTML>'],
                '4.01' => [
                    'strict' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
                    'transitional' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
                    'frameset' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">'
                ],
                '2.0' => ['dtd' => '<!DOCTYPE html PUBLIC "-//IETF//DTD HTML 2.0//EN">'],
                '3.2' => ['dtd' => '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">']
            ],
            'xhtml' => [
                '1.0' => [
                    'dtd' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.0//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic10.dtd">',
                    'strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
                    'transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
                    'frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">'
                ],
                '1.1' => [
                    'dtd' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
                    'basic' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.1//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd">'
                ]
            ],
            'mathml' => [
                '2.0' => ['dtd' => '<!DOCTYPE math PUBLIC "-//W3C//DTD MathML 2.0//EN" "http://www.w3.org/TR/MathML2/dtd/mathml2.dtd">'],
                '1.1' => ['dtd' => '<!DOCTYPE math SYSTEM "http://www.w3.org/Math/DTD/mathml1/mathml.dtd">']
            ],
            'xhtml_mathml_svg' => [
                '1.1' => [
                    'basic' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN" "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">',
                    'xhtml' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN" "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">',
                    'svg' => '<!DOCTYPE svg:svg PUBLIC "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN" "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">'
                ]
            ],
            'svg' => [
                '1.1' => [
                    'full' => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">',
                    'basic' => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1 Basic//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11-basic.dtd">',
                    'tiny' => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1 Tiny//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11-tiny.dtd">'
                ],
                '1.0' => ['dtd' => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.0//EN" "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd">']
            ]
        ];
        if (!isset($doc_types[$doctype], $doc_types[$doctype][$version], $doc_types[$doctype][$version][$type])) {
            trigger_error('Non-existing doctype requested');
        }
        $this->doctype = $doc_types[$doctype][$version][$type];
        return $this;
    }

    /**
     * @param $title
     * @return $this$this
     */
    public function setTitle($title)
    {
        $this->title = strip_tags($title);
        return $this;
    }

    /**
     * @param string $f
     * @param string $type
     * @return PageHead$this
     */
    public function setFavicon($f, $type = 'image/x-icon')
    {
        if (!$type) {
            $ext = pathinfo($f, PATHINFO_EXTENSION);
            $type = MimeTypes::getMimeType($ext ? $ext : NULL);
        }
        $this->favicon = ['href' => $f, 'type' => $type];
        return $this;
    }

    /**
     * @param $rss
     * @param $title
     */
    public function addRSSFeed($rss, $title)
    {
        $this->rss[] = ['href' => $rss, 'title' => $title];
    }

    /**
     * Add custom string (element) into <head>
     * @param $string
     * @return $this
     */
    public function addCustomString($string)
    {
        $this->custom_strings[] = $string;

        return $this;
    }

    /**
     * @param string $url
     * @param string $media
     * @return $this$this
     */
    public function addCssUrl($url, $media = 'all')
    {
        $this->css_urls[$url] = $media;
        return $this;
    }

    /**
     * @param string $url
     * @return $this$this
     */
    public function addJsUrl($url)
    {
        if (!in_array($url, $this->js_urls)) {
            $this->js_urls[++$this->js_sequence] = $url;
        }
        return $this;
    }

    /**
     * @param string $js
     * @return $this$this
     */
    public function addJs($js)
    {
        $this->js[++$this->js_sequence] = $js;
        return $this;
    }

    /**
     * @param string $css
     * @return $this$this
     */
    public function addCss($css)
    {
        $this->css[] = $css;
        return $this;
    }

    /**
     * @param $content
     * @param string $name
     * @param string $http_equiv
     * @param string $property
     * @return $this
     */
    public function addMeta($content, $name = '', $http_equiv = '', $property = '')
    {
        $this->meta[] = [
            'content' => $content,
            'name' => $name,
            'http_equiv' => $http_equiv,
            'property' => $property
        ];
        return $this;
    }

    /**
     * @param string $kw
     * @return $this
     */
    public function setMetaKeywords($kw)
    {
        $this->keywords = $kw;
        return $this;
    }

    /**
     * @param string $dsc
     * @return $this
     */
    public function setMetaDescription($dsc)
    {
        $this->description = $dsc;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSslState()
    {
        return $this->ssl;
    }

    /**
     * @param bool $flag
     * @return $this
     */
    public function setSslState($flag)
    {
        $this->ssl = (bool)$flag;
        return $this;
    }

    /**
     * @param string $html_tag
     * @return $this
     */
    public function replaceStandardHtmlTag($html_tag)
    {
        $this->replace_for_standard_html_tag = $html_tag;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        ob_start();

        echo $this->doctype . "\n";
        if ($this->replace_for_standard_html_tag):
            echo $this->replace_for_standard_html_tag;
        else:
            ?><html<?= ($this->html_tag_attributes ? ' ' . implode(' ', $this->html_tag_attributes) : '') ?>><?php
        endif;
        ?>

        <head>
            <?php if (!Settings::get('do_not_expose_generator')): ?>
                <meta name="generator" content="<?= CMS_NAME ?>, <?= CMS_SITE ?>"><?php endif; ?>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <meta charset="utf-8">
            <title><?= htmlspecialchars($this->title, ENT_QUOTES) ?></title><?php
            // META
            foreach ($this->meta as $v): ?>
                <meta<?= ($v['name'] ? ' name="' . $v['name'] . '" ' : '') . ($v['http_equiv'] ? ' http-equiv="' . $v['http_equiv'] . '"' : '') . ($v['property'] ? ' property="' . $v['property'] . '"' : '') ?> content="<?= $v['content'] ?>">
            <?php endforeach;

            // CSS files
            foreach ($this->css_urls as $k => $v): $k = Finder::getInstance()->searchForRealPath($k); ?>
                <link rel="stylesheet" type="text/css" href="<?= $k ?>" media="<?= $v ?>">
            <?php endforeach;

            // CSS files
            foreach ($this->css as $v): ?>
                <style>
                    <?= $v ?>
                </style>
            <?php endforeach;

            // JS files and scripts
            for ($i = 1; $i <= $this->js_sequence; $i++) :
                if (isset($this->js_urls[$i])): $this->js_urls[$i] = Finder::getInstance()->searchForRealPath($this->js_urls[$i]); ?>
                    <script src="<?= $this->js_urls[$i] ?>"></script>
                <?php elseif (isset($this->js[$i])): ?>
                    <script><?= $this->js[$i] ?></script>
                <?php endif;
            endfor;

            // RSS feeds
            foreach ($this->rss as $v): ?>
                <link rel="alternate" type="application/rss+xml"
                      title="<?= htmlspecialchars($v['title'], ENT_QUOTES) ?>" href="<?= $v['href'] ?>">
            <?php endforeach;

            // RSS feeds
            if ($this->apple_touch_icon_url): ?>
                <link rel="apple-touch-icon"
                      href="<?= Finder::getInstance()->searchForRealPath($this->apple_touch_icon_url) ?>">
            <?php endif;

            // META keywords
            if ($this->keywords): ?>
                <meta name="keywords" content="<?= htmlspecialchars($this->keywords, ENT_QUOTES) ?>">
            <?php endif;

            // META description
            if ($this->description): ?>
                <meta name="description" content="<?= htmlspecialchars($this->description, ENT_QUOTES) ?>">
            <?php endif;

            // Any custom string appended into <head>
            foreach ($this->custom_strings as $v): ?>
                <?= $v ?>
            <?php endforeach;

            // Favicon
            if ($this->favicon) :
                $this->favicon['href'] = ltrim($this->favicon['href'], '/'); ?>
                <link rel="icon" href="http<?= ($this->ssl ? 's' : '') ?>://<?= CFG_DOMAIN . '/' . $this->favicon['href'] ?>" type="<?= $this->favicon['type'] ?>">
                <link rel="shortcut icon" href="http<?= ($this->ssl ? 's' : '') ?>://<?= CFG_DOMAIN . '/' . $this->favicon['href'] ?>" type="<?= $this->favicon['type'] ?>">
                <?php
            endif;

            // Google Analytics
            if (($ga = Settings::get('google_analytics_code'))): ?>
                <script>
                    (function (i, s, o, g, r, a, m) {
                        i['GoogleAnalyticsObject'] = r;
                        i[r] = i[r] || function () {
                                (i[r].q = i[r].q || []).push(arguments)
                            }, i[r].l = 1 * new Date();
                        a = s.createElement(o),
                            m = s.getElementsByTagName(o)[0];
                        a.async = 1;
                        a.src = g;
                        m.parentNode.insertBefore(a, m)
                    })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

                    ga('create', 'UA-<?=$ga?>', '<?=CFG_DOMAIN?>');
                    ga('send', 'pageview');

                </script>
            <?php endif;
            unset($ga); ?>
        </head>
        <?php

        return ob_get_clean();
    }
}