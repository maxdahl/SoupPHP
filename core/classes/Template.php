<?php

namespace Soup\Core;

use Oil\Exception;

defined('ROOT') or die('No direct script access');

class Template
{
    protected $file;
    protected $filePath;

    public function __construct($file)
    {
        $this->file = $file;
        $this->filePath = pathinfo($file)['dirname'];
    }

    public function __toString()
    {
        return $this->parse();
    }

    public function parse($file = null)
    {

        if ($file === null)
            $file = $this->file;

        $content = $this->extractContent($file);
        $content = $this->parseComments($content);
        $content = $this->parseIncludes($content);
        $content = $this->parseFunctions($content);
        $content = $this->parseVariables($content);
        $content = $this->parseFor($content);
        $content = $this->parseIf($content);

        return $this->minify($content);
    }

    public function compile($file = null)
    {
        if ($file === null)
            $file = $this->file;

        $fInfo = pathinfo($file);

        $path = $fInfo['dirname'] . DS;

        $activeDir = defined('MODPATH') ? MODPATH : APP;

        $path = str_replace($activeDir . 'views', $activeDir . 'views' . DS . 'compiled', $path);
        $fileName = $fInfo['filename'] . '.cpl.php';

        if (is_dir($path) === false)
            mkdir($path, 0777, true);

        $data = $this->parse($file);

        $recompile = ENVIRONMENT === 'TESTING';
        if (!$recompile && file_exists($path . $fileName)) {
            $lastModifiedOriginal = filemtime($file);
            $compiledAt = filemtime($path . $fileName);

            if ($lastModifiedOriginal < $compiledAt)
                return $path . $fileName;
        }

        if (file_put_contents($path . $fileName, $data) === false) {
            throw new \Exception('Unable to write compiled template file (' . $path . ')');
        }

        return $path . $fileName;
    }

    protected function parseComments($content)
    {
        $regex = '/{#\s*(.*)\s*#}/i';
        return preg_replace($regex, '', $content);
    }

    protected function parseIncludes($content)
    {
        $regex = '/{%\s*include\(("|\')([a-z0-9_\-\/\\\.]+)\1\)\s*%}/i';
        preg_match_all($regex, $content, $matches);

        for ($i = 0; $i < count($matches[0]); $i++) {
            $includeFile = $this->filePath . DS . $matches[2][$i] . '.twig';
            $compiledIncludeFile = $this->compile($includeFile);

            $content = str_replace($matches[0][$i], '<?php include(\'' . $compiledIncludeFile . '\'); ?>', $content);
        }

        return $content;
    }

    protected function parseVariables($content)
    {
        $regex = '/{{\s*([0-9a-z_]+)\s*}}/i';
        preg_match_all($regex, $content, $matches);

        for ($i = 0; $i < count($matches[0]); $i++) {
            $var = \Str::removeSpaces($matches[1][$i]);
            $content = str_replace($matches[0][$i], '<?php echo $' . $var . '; ?>', $content);
        }

        $content = $this->parseArrays($content);
        $content = $this->parseObjects($content);

        return $content;
    }

    protected function parseArrays($content)
    {
        $regex = '/{{\s*([0-9a-z_]+\[.+\])\s*}}/i';
        preg_match_all($regex, $content, $matches);

        for ($i = 0; $i < count($matches[0]); $i++) {
            $var = \Str::removeSpaces($matches[1][$i]);
            $content = str_replace($matches[0][$i], '<?php echo $' . $var . '; ?>', $content);
        }

        return $content;
    }

    protected function parseObjects($content)
    {
        $regex = '/{{\s*([^{}]+\.[^{}]*)\s*}}/i';
        preg_match_all($regex, $content, $matches);

        for ($i = 0; $i < count($matches[0]); $i++) {
            $var = \Str::removeSpaces($matches[1][$i]);
            $var = str_replace('.', '->', $var);
            $content = str_replace($matches[0][$i], '<?php echo $' . $var . '; ?>', $content);
        }

        return $content;
    }

    protected function parseFor($content)
    {
        $startPattern = '/{%\s*for\s+([^\s=>]+)\s+as\s+([^\s=>]+)(\s*=>\s*([^\s=>\[\]]+))?\s*%}/i';
        $endPattern = '/{%\s*endfor\s*%}/i';

        preg_match_all($startPattern, $content, $startMatches);
        preg_match_all($endPattern, $content, $endMatches);

        if (count($startMatches[0]) !== count($endMatches[0]))
            throw new \Exception('Syntax Error: Loop not closed in ' . $this->file);

        for ($i = 0; $i < count($startMatches[0]); $i++) {
            $loopName = $startMatches[0][$i];
            $var = $startMatches[1][$i];
            $key = $startMatches[2][$i];
            $val = count($startMatches) > 3 ? $startMatches[4][$i] : '';

            $var = $this->sanitizeIfVar($var);

            $expression = '<?php foreach('
                . $var . ' as '
                . '$' . $key;

            if ($val !== '')
                $expression .= ' => $' . $val;
            $expression .= '): ?>';

            $content = \Soup\Helper\Str::replaceFirst($loopName, $expression, $content);
            $content = \Soup\Helper\Str::replaceFirst($endMatches[0][$i], '<?php endforeach; ?>', $content);
        }

        return $content;
    }

    protected function parseIf($content)
    {
        $startPattern = '/{%\s*if\s+(.+)\s*%}/i';
        $elseIfPattern = '/{%\s*elseif\s+(.+)\s*%}/i';
        $elsePattern = '/{%\s*else\s*%}/i';
        $endPattern = '/{%\s*endif\s*%}/i';

        preg_match_all($startPattern, $content, $startMatches);
        preg_match_all($elseIfPattern, $content, $elseIfMatches);

        $content = preg_replace($elsePattern, '<?php else: ?>', $content);
        $content = preg_replace($endPattern, '<?php endif; ?>', $content);

        for ($i = 0; $i < count($startMatches[0]); $i++) {

            $condition = $startMatches[1][$i];
            $statement = '<?php if(' . $this->compileIfConditions($condition) . '): ?>';
            $content = \Soup\Helper\Str::replaceFirst($startMatches[0][$i], $statement, $content);
        }

        for ($i = 0; $i < count($elseIfMatches[0]); $i++) {

            $condition = $elseIfMatches[1][$i];
            $statement = '<?php elseif(' . $this->compileIfConditions($condition) . '): ?>';
            $content = \Soup\Helper\Str::replaceFirst($elseIfMatches[0][$i], $statement, $content);
        }

        return $content;

    }

    protected function compileIfConditions($condition)
    {
        $conditionRegex = '/([()a-z0-9.\[\]"\']+)\s*([=!<>]{1,3})\s*([()a-z0-9.\[\]"\']+)/';

        preg_match_all('/[|&]{1,2}/i', $condition, $logicMatches);
        preg_match_all($conditionRegex, $condition, $condMatches);

        $logicCount = count($logicMatches[0]);
        $statement = '';

        for ($i = 0; $i < count($condMatches[0]); $i++) {

            $var1 = $condMatches[1][$i];
            $var2 = $condMatches[3][$i];
            $operator = $condMatches[2][$i];

            $var1 = $this->sanitizeIfVar($var1);
            $var2 = $this->sanitizeIfVar($var2);

            $statement .= $var1 . ' ' . $operator . ' ' . $var2;

            if ($i < $logicCount) {
                $statement .= ' ' . $logicMatches[0][$i] . ' ';
            }
        }

        return '' . $statement;
    }

    protected function sanitizeIfVar($var)
    {
        $keywords = [
            'true', 'false', 'null', 'TRUE', 'FALSE', 'NULL'
        ];

        $pre = '';
        $post = '';

        $var = trim($var);

        if (strpos($var, '(') !== false) {
            $var = str_replace('(', '', $var);
            $pre = '(';
        }
        if (strpos($var, ')') !== false) {
            $var = str_replace(')', '', $var);
            $post = ')';
        }

        if (!in_array($var, $keywords) && $var != '' && !is_numeric($var)
            && strpos($var, '[') === false
            && \Soup\Helper\Str::contains($var, ['\'', '"']) === false) {

            $var = str_replace('.', '->', $var);
            $var = '$' . $var;
        }

        return $pre . $var . $post;
    }

    protected function parseFunctions($content)
    {
        $regex = '/{{\s*([\a-z0-9_]+)\((.*)\)\s*}}/i';

        preg_match_all($regex, $content, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {

            $arguments = explode(',', $matches[2][$i]);
            $sanitizedArgs = [];

            foreach ($arguments as $arg)
                $sanitizedArgs[] = $this->sanitizeIfVar($arg);

            $content = \Soup\Helper\Str::replaceFirst($matches[0][$i], '<?php echo ' . $matches[1][$i] . '(' . implode(', ', $sanitizedArgs) . '); ?>', $content);
        }

        return $content;
    }

    protected function sanitizeArrayIndex($index)
    {
        $index = str_replace('[', '', $index);
        $index = str_replace(']', '', $index);

        if (\Soup\Helper\Str::contains($index, ['\'', '"']) === false) {
            return (int)$index;
        } else
            return trim($index, '"\' ');
    }

    protected function minify($data)
    {
        $search = array(
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
            '/<!--(.|\s)*?-->/' // Remove HTML comments
        );

        $replace = array(
            '>',
            '<',
            '\\1',
            ''
        );

        $data = preg_replace($search, $replace, $data);
        return $data;
    }


    protected function extractContent($file)
    {
        return file_get_contents($file);
    }
}