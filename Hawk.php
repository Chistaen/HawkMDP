<?php
/**
 * Hawk Markdown Parser - Markdown parser adapted for wiki usage
 *
 * @version     1.0.0
 * @copyright   2017 Robert Monden <dev [at] robertmonden [dot] com>
 * @license     MIT
 *
 * This file contains code from:
 * ------------------------------------------
 * Parsedown
 * http://parsedown.org
 *
 * (c) Emanuil Rusev
 * http://erusev.com
 *
 * For the full license information, view the LICENSE file that was distributed
 * with this source code.
*/

class Hawk
{
    const version = '1.0.0';

    function text($text)
    {
        # make sure no definitions are set
        $this->definitionData = array();

        # standardize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        # remove surrounding line breaks
        $text = trim($text, "\n");

        # split text into lines
        $lines = explode("\n", $text);

        # iterate through lines to identify blocks
        $markup = $this->lines($lines);

        # trim line breaks
        $markup = trim($markup, "\n");

        return $markup;
    }

    function setBreaksEnabled($breaksEnabled)
    {
        $this->breaksEnabled = $breaksEnabled;

        return $this;
    }

    protected $breaksEnabled;

    function setMarkupEscaped($markupEscaped)
    {
        $this->markupEscaped = $markupEscaped;

        return $this;
    }

    protected $markupEscaped;

    function setUrlsLinked($urlsLinked)
    {
        $this->urlsLinked = $urlsLinked;

        return $this;
    }

    protected $urlsLinked = true;

    protected $blockTypes = array(
        '#' => array('Header'),
        '*' => array('Rule', 'List'),
        '+' => array('List'),
        '-' => array('SetextHeader', 'Table', 'Rule', 'List'),
        '0' => array('List'),
        '1' => array('List'),
        '2' => array('List'),
        '3' => array('List'),
        '4' => array('List'),
        '5' => array('List'),
        '6' => array('List'),
        '7' => array('List'),
        '8' => array('List'),
        '9' => array('List'),
        ':' => array('Table'),
        '<' => array('Comment', 'Markup'),
        '=' => array('SetextHeader'),
        '>' => array('Quote'),
        '[' => array('Reference'),
        '_' => array('Rule'),
        '`' => array('FencedCode'),
        '|' => array('Table'),
        '~' => array('FencedCode'),
    );

    # ~

    protected $unmarkedBlockTypes = array(
        'Code',
    );

    protected function lines(array $lines)
    {
        $currentBlock = null;

        foreach ($lines as $line)
        {
            if (chop($line) === '')
            {
                if (isset($currentBlock))
                {
                    $currentBlock['interrupted'] = true;
                }

                continue;
            }

            if (strpos($line, "\t") !== false)
            {
                $parts = explode("\t", $line);

                $line = $parts[0];

                unset($parts[0]);

                foreach ($parts as $part)
                {
                    $shortage = 4 - mb_strlen($line, 'utf-8') % 4;

                    $line .= str_repeat(' ', $shortage);
                    $line .= $part;
                }
            }

            $indent = 0;

            while (isset($line[$indent]) and $line[$indent] === ' ')
            {
                $indent++;
            }

            $text = $indent > 0 ? substr($line, $indent) : $line;

            $line = array('body' => $line, 'indent' => $indent, 'text' => $text);

            if (isset($currentBlock['continuable']))
            {
                $block = $this->{'block'.$currentBlock['type'].'Continue'}($line, $currentBlock);

                if (isset($block))
                {
                    $currentBlock = $block;

                    continue;
                }
                else
                {
                    if ($this->isBlockCompletable($currentBlock['type']))
                    {
                        $currentBlock = $this->{'block'.$currentBlock['type'].'Complete'}($currentBlock);
                    }
                }
            }

            $marker = $text[0];

            $blockTypes = $this->unmarkedBlockTypes;

            if (isset($this->blockTypes[$marker]))
            {
                foreach ($this->blockTypes[$marker] as $blockType)
                {
                    $blockTypes []= $blockType;
                }
            }

            foreach ($blockTypes as $blockType)
            {
                $block = $this->{'block'.$blockType}($line, $currentBlock);

                if (isset($block))
                {
                    $block['type'] = $blockType;

                    if ( ! isset($block['identified']))
                    {
                        $blocks[] = $currentBlock;

                        $block['identified'] = true;
                    }

                    if ($this->isBlockContinuable($blockType))
                    {
                        $block['continuable'] = true;
                    }

                    $currentBlock = $block;

                    continue 2;
                }
            }

            # ~

            if (isset($currentBlock) and ! isset($currentBlock['type']) and ! isset($currentBlock['interrupted']))
            {
                $currentBlock['element']['text'] .= "\n".$text;
            }
            else
            {
                $blocks []= $currentBlock;

                $currentBlock = $this->paragraph($line);

                $currentBlock['identified'] = true;
            }
        }

        if (isset($currentBlock['continuable']) and $this->isBlockCompletable($currentBlock['type']))
        {
            $currentBlock = $this->{'block'.$currentBlock['type'].'Complete'}($currentBlock);
        }

        $blocks []= $currentBlock;

        unset($blocks[0]);

        $markup = '';

        foreach ($blocks as $block)
        {
            if (isset($block['hidden']))
            {
                continue;
            }

            $markup .= "\n";
            $markup .= isset($block['markup']) ? $block['markup'] : $this->element($block['element']);
        }

        $markup .= "\n";

        return $markup;
    }

    protected function isBlockContinuable($type)
    {
        return method_exists($this, 'block'.$type.'Continue');
    }

    protected function isBlockCompletable($type)
    {
        return method_exists($this, 'block'.$type.'Complete');
    }

    protected function blockCode($line, $block = null)
    {
        if (isset($block) and ! isset($block['type']) and ! isset($block['interrupted']))
        {
            return null;
        }

        if ($line['indent'] >= 4)
        {
            $text = substr($line['body'], 4);

            $block = array(
                'element' => array(
                    'name' => 'pre',
                    'handler' => 'element',
                    'text' => array(
                        'name' => 'code',
                        'text' => $text,
                    ),
                ),
            );

            return $block;
        }
    }

    protected function blockCodeContinue($line, $block)
    {
        if ($line['indent'] >= 4)
        {
            if (isset($block['interrupted']))
            {
                $block['element']['text']['text'] .= "\n";

                unset($block['interrupted']);
            }

            $block['element']['text']['text'] .= "\n";

            $text = substr($line['body'], 4);

            $block['element']['text']['text'] .= $text;

            return $block;
        }
    }

    protected function blockCodeComplete($block)
    {
        $text = $block['element']['text']['text'];

        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

        $block['element']['text']['text'] = $text;

        return $block;
    }

    protected function blockComment($line)
    {
        if ($this->markupEscaped)
        {
            return null;
        }

        if (isset($line['text'][3]) and $line['text'][3] === '-' and $line['text'][2] === '-' and $line['text'][1] === '!')
        {
            $block = array(
                'markup' => $line['body'],
            );

            if (preg_match('/-->$/', $line['text']))
            {
                $block['closed'] = true;
            }

            return $block;
        }
    }

    protected function blockCommentContinue($line, array $block)
    {
        if (isset($block['closed']))
        {
            return null;
        }

        $block['markup'] .= "\n" . $line['body'];

        if (preg_match('/-->$/', $line['text']))
        {
            $block['closed'] = true;
        }

        return $block;
    }

    protected function blockFencedCode($line)
    {
        if (preg_match('/^['.$line['text'][0].']{3,}[ ]*([\w-]+)?[ ]*$/', $line['text'], $matches))
        {
            $element = array(
                'name' => 'code',
                'text' => '',
            );

            if (isset($matches[1]))
            {
                $class = 'language-'.$matches[1];

                $element['attributes'] = array(
                    'class' => $class,
                );
            }

            $block = array(
                'char' => $line['text'][0],
                'element' => array(
                    'name' => 'pre',
                    'handler' => 'element',
                    'text' => $element,
                ),
            );

            return $block;
        }
    }

    protected function blockFencedCodeContinue($line, $block)
    {
        if (isset($block['complete']))
        {
            return null;
        }

        if (isset($block['interrupted']))
        {
            $block['element']['text']['text'] .= "\n";

            unset($block['interrupted']);
        }

        if (preg_match('/^'.$block['char'].'{3,}[ ]*$/', $line['text']))
        {
            $block['element']['text']['text'] = substr($block['element']['text']['text'], 1);

            $block['complete'] = true;

            return $block;
        }

        $block['element']['text']['text'] .= "\n".$line['body'];

        return $block;
    }

    protected function blockFencedCodeComplete($block)
    {
        $text = $block['element']['text']['text'];

        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

        $block['element']['text']['text'] = $text;

        return $block;
    }

    protected function blockHeader($line)
    {
        if (isset($line['text'][1]))
        {
            $level = 1;

            while (isset($line['text'][$level]) and $line['text'][$level] === '#')
            {
                $level ++;
            }

            if ($level > 6)
            {
                return null;
            }

            $text = trim($line['text'], '# ');

            $block = array(
                'element' => array(
                    'name' => 'h' . min(6, $level),
                    'text' => $text,
                    'handler' => 'line',
                ),
            );

            return $block;
        }

        return [];
    }

    protected function blockList($line)
    {
        list($name, $pattern) = $line['text'][0] <= '-' ? array('ul', '[*+-]') : array('ol', '[0-9]+[.]');

        if (preg_match('/^('.$pattern.'[ ]+)(.*)/', $line['text'], $matches))
        {
            $block = array(
                'indent' => $line['indent'],
                'pattern' => $pattern,
                'element' => array(
                    'name' => $name,
                    'handler' => 'elements',
                ),
            );

            if($name === 'ol') 
            {
                $listStart = stristr($matches[0], '.', true);
                
                if($listStart !== '1')
                {
                    $block['element']['attributes'] = array('start' => $listStart);
                }
            }

            $block['li'] = array(
                'name' => 'li',
                'handler' => 'li',
                'text' => array(
                    $matches[2],
                ),
            );

            $block['element']['text'] []= & $block['li'];

            return $block;
        }
    }

    protected function blockListContinue($line, array $block)
    {
        if ($block['indent'] === $line['indent'] and preg_match('/^'.$block['pattern'].'(?:[ ]+(.*)|$)/', $line['text'], $matches))
        {
            if (isset($block['interrupted']))
            {
                $block['li']['text'] []= '';

                unset($block['interrupted']);
            }

            unset($block['li']);

            $text = isset($matches[1]) ? $matches[1] : '';

            $block['li'] = array(
                'name' => 'li',
                'handler' => 'li',
                'text' => array(
                    $text,
                ),
            );

            $block['element']['text'] []= & $block['li'];

            return $block;
        }

        if ($line['text'][0] === '[' and $this->blockReference($line))
        {
            return $block;
        }

        if ( ! isset($block['interrupted']))
        {
            $text = preg_replace('/^[ ]{0,4}/', '', $line['body']);

            $block['li']['text'] []= $text;

            return $block;
        }

        if ($line['indent'] > 0)
        {
            $block['li']['text'] []= '';

            $text = preg_replace('/^[ ]{0,4}/', '', $line['body']);

            $block['li']['text'] []= $text;

            unset($block['interrupted']);

            return $block;
        }

        return [];
    }

    protected function blockQuote($line)
    {
        if (preg_match('/^>[ ]?(.*)/', $line['text'], $matches))
        {
            $block = array(
                'element' => array(
                    'name' => 'blockquote',
                    'handler' => 'lines',
                    'text' => (array) $matches[1],
                ),
            );

            return $block;
        }

        return [];
    }

    protected function blockQuoteContinue($line, array $block)
    {
        if ($line['text'][0] === '>' and preg_match('/^>[ ]?(.*)/', $line['text'], $matches))
        {
            if (isset($block['interrupted']))
            {
                $block['element']['text'] []= '';

                unset($block['interrupted']);
            }

            $block['element']['text'] []= $matches[1];

            return $block;
        }

        if ( ! isset($block['interrupted']))
        {
            $block['element']['text'] []= $line['text'];

            return $block;
        }

        return [];
    }

    protected function blockRule($line)
    {
        if (preg_match('/^(['.$line['text'][0].'])([ ]*\1){2,}[ ]*$/', $line['text']))
        {
            $block = array(
                'element' => array(
                    'name' => 'hr'
                ),
            );

            return $block;
        }

        return [];
    }

    protected function blockSetextHeader($line, array $block = null)
    {
        if ( ! isset($block) or isset($block['type']) or isset($block['interrupted']))
        {
            return null;
        }

        if (chop($line['text'], $line['text'][0]) === '')
        {
            $block['element']['name'] = $line['text'][0] === '=' ? 'h1' : 'h2';

            return $block;
        }

        return [];
    }

    protected function blockMarkup($line)
    {
        if ($this->markupEscaped)
        {
            return null;
        }

        if (preg_match('/^<(\w*)(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*(\/)?>/', $line['text'], $matches))
        {
            $element = strtolower($matches[1]);

            if (in_array($element, $this->textLevelElements))
            {
                return null;
            }

            $block = array(
                'name' => $matches[1],
                'depth' => 0,
                'markup' => $line['text'],
            );

            $length = strlen($matches[0]);

            $remainder = substr($line['text'], $length);

            if (trim($remainder) === '')
            {
                if (isset($matches[2]) or in_array($matches[1], $this->voidElements))
                {
                    $block['closed'] = true;

                    $block['void'] = true;
                }
            }
            else
            {
                if (isset($matches[2]) or in_array($matches[1], $this->voidElements))
                {
                    return null;
                }

                if (preg_match('/<\/'.$matches[1].'>[ ]*$/i', $remainder))
                {
                    $block['closed'] = true;
                }
            }

            return $block;
        }

        return [];
    }

    protected function blockMarkupContinue($line, array $block)
    {
        if (isset($block['closed']))
        {
            return null;
        }

        if (preg_match('/^<'.$block['name'].'(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*>/i', $line['text'])) # open
        {
            $block['depth'] ++;
        }

        if (preg_match('/(.*?)<\/'.$block['name'].'>[ ]*$/i', $line['text'], $matches)) # close
        {
            if ($block['depth'] > 0)
            {
                $block['depth'] --;
            }
            else
            {
                $block['closed'] = true;
            }
        }

        if (isset($block['interrupted']))
        {
            $block['markup'] .= "\n";

            unset($block['interrupted']);
        }

        $block['markup'] .= "\n".$line['body'];

        return $block;
    }

    protected function blockReference($line)
    {
        if (preg_match('/^\[(.+?)\]:[ ]*<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*$/', $line['text'], $matches))
        {
            $id = strtolower($matches[1]);

            $data = array(
                'url' => $matches[2],
                'title' => null,
            );

            if (isset($matches[3]))
            {
                $dData['title'] = $matches[3];
            }

            $this->definitionData['Reference'][$id] = $data;

            $block = array(
                'hidden' => true,
            );

            return $block;
        }

        return null;
    }

    protected function blockTable($line, array $block = null)
    {
        if ( ! isset($block) or isset($block['type']) or isset($block['interrupted']))
        {
            return null;
        }

        if (strpos($block['element']['text'], '|') !== false and chop($line['text'], ' -:|') === '')
        {
            $alignments = array();

            $divider = $line['text'];

            $divider = trim($divider);
            $divider = trim($divider, '|');

            $dividerCells = explode('|', $divider);

            foreach ($dividerCells as $dividerCell)
            {
                $dividerCell = trim($dividerCell);

                if ($dividerCell === '')
                {
                    continue;
                }

                $alignment = null;

                if ($dividerCell[0] === ':')
                {
                    $alignment = 'left';
                }

                if (substr($dividerCell, - 1) === ':')
                {
                    $alignment = $alignment === 'left' ? 'center' : 'right';
                }

                $alignments []= $alignment;
            }

            $headerElements = array();

            $header = $block['element']['text'];

            $header = trim($header);
            $header = trim($header, '|');

            $headerCells = explode('|', $header);

            foreach ($headerCells as $index => $headerCell)
            {
                $headerCell = trim($headerCell);

                $headerElement = array(
                    'name' => 'th',
                    'text' => $headerCell,
                    'handler' => 'line',
                );

                if (isset($alignments[$index]))
                {
                    $alignment = $alignments[$index];

                    $headerElement['attributes'] = array(
                        'style' => 'text-align: '.$alignment.';',
                    );
                }

                $headerElements[] = $headerElement;
            }

            $block = array(
                'alignments' => $alignments,
                'identified' => true,
                'element' => array(
                    'name' => 'table',
                    'handler' => 'elements',
                ),
            );

            $block['element']['text'] []= array(
                'name' => 'thead',
                'handler' => 'elements',
            );

            $block['element']['text'] []= array(
                'name' => 'tbody',
                'handler' => 'elements',
                'text' => array(),
            );

            $block['element']['text'][0]['text'] []= array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $headerElements,
            );

            return $block;
        }

        return [];
    }

    protected function blockTableContinue($line, array $block)
    {
        if (isset($block['interrupted']))
        {
            return null;
        }

        if ($line['text'][0] === '|' or strpos($line['text'], '|'))
        {
            $elements = array();

            $row = $line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);

            foreach ($matches[0] as $index => $cell)
            {
                $cell = trim($cell);

                $element = array(
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => $cell,
                );

                if (isset($block['alignments'][$index]))
                {
                    $element['attributes'] = array(
                        'style' => 'text-align: '.$block['alignments'][$index].';',
                    );
                }

                $elements []= $element;
            }

            $element = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $elements,
            );

            $block['element']['text'][1]['text'] []= $element;

            return $block;
        }

        return [];
    }

    protected function paragraph($line)
    {
        $block = array(
            'element' => array(
                'name' => 'p',
                'text' => $line['text'],
                'handler' => 'line',
            ),
        );

        return $block;
    }

    protected $inlineTypes = array(
        '"' => array('SpecialCharacter'),
        '!' => array('Image'),
        '&' => array('SpecialCharacter'),
        '*' => array('Emphasis'),
        ':' => array('Url'),
        '<' => array('UrlTag', 'EmailTag', 'Markup', 'SpecialCharacter'),
        '>' => array('SpecialCharacter'),
        '[' => array('Link'),
        '_' => array('Emphasis'),
        '`' => array('Code'),
        '~' => array('Strikethrough'),
        '\\' => array('EscapeSequence'),
    );

    protected $inlineMarkerList = '!"*_&[:<>`~\\';

    public function line($text)
    {
        $markup = '';

        # $excerpt is based on the first occurrence of a marker

        while ($excerpt = strpbrk($text, $this->inlineMarkerList))
        {
            $marker = $excerpt[0];

            $markerPosition = strpos($text, $marker);

            $excerpt = array('text' => $excerpt, 'context' => $text);

            foreach ($this->inlineTypes[$marker] as $inlineType)
            {
                $Inline = $this->{'inline'.$inlineType}($excerpt);

                if ( ! isset($Inline))
                {
                    continue;
                }

                # makes sure that the inline belongs to "our" marker

                if (isset($Inline['position']) and $Inline['position'] > $markerPosition)
                {
                    continue;
                }

                # sets a default inline position

                if ( ! isset($Inline['position']))
                {
                    $Inline['position'] = $markerPosition;
                }

                # the text that comes before the inline
                $unmarkedText = substr($text, 0, $Inline['position']);

                # compile the unmarked text
                $markup .= $this->unmarkedText($unmarkedText);

                # compile the inline
                $markup .= isset($Inline['markup']) ? $Inline['markup'] : $this->element($Inline['element']);

                # remove the examined text
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2;
            }

            # the marker does not belong to an inline

            $unmarkedText = substr($text, 0, $markerPosition + 1);

            $markup .= $this->unmarkedText($unmarkedText);

            $text = substr($text, $markerPosition + 1);
        }

        $markup .= $this->unmarkedText($text);

        return $markup;
    }

    protected function inlineCode($excerpt)
    {
        $marker = $excerpt['text'][0];

        if (preg_match('/^('.$marker.'+)[ ]*(.+?)[ ]*(?<!'.$marker.')\1(?!'.$marker.')/s', $excerpt['text'], $matches))
        {
            $text = $matches[2];
            $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
            $text = preg_replace("/[ ]*\n/", ' ', $text);

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'code',
                    'text' => $text,
                ),
            );
        }

        return [];
    }

    protected function inlineEmailTag($excerpt)
    {
        if (strpos($excerpt['text'], '>') !== false and preg_match('/^<((mailto:)?\S+?@\S+?)>/i', $excerpt['text'], $matches))
        {
            $url = $matches[1];

            if ( ! isset($matches[2]))
            {
                $url = 'mailto:' . $url;
            }

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'a',
                    'text' => $matches[1],
                    'attributes' => array(
                        'href' => $url,
                    ),
                ),
            );
        }

        return [];
    }

    protected function inlineEmphasis($excerpt)
    {
        if ( ! isset($excerpt['text'][1]))
        {
            return null;
        }

        $marker = $excerpt['text'][0];

        if ($excerpt['text'][1] === $marker and preg_match($this->strongRegex[$marker], $excerpt['text'], $matches))
        {
            $emphasis = 'strong';
        }
        elseif (preg_match($this->emRegex[$marker], $excerpt['text'], $matches))
        {
            $emphasis = 'em';
        }
        else
        {
            return null;
        }

        return array(
            'extent' => strlen($matches[0]),
            'element' => array(
                'name' => $emphasis,
                'handler' => 'line',
                'text' => $matches[1],
            ),
        );
    }

    protected function inlineEscapeSequence($excerpt)
    {
        if (isset($excerpt['text'][1]) and in_array($excerpt['text'][1], $this->specialCharacters))
        {
            return array(
                'markup' => $excerpt['text'][1],
                'extent' => 2,
            );
        }

        return [];
    }

    protected function inlineImage($excerpt)
    {
        if ( ! isset($excerpt['text'][1]) or $excerpt['text'][1] !== '[')
        {
            return null;
        }

        $excerpt['text']= substr($excerpt['text'], 1);

        $link = $this->inlineLink($excerpt);

        if ($link === null)
        {
            return null;
        }

        $inline = array(
            'extent' => $link['extent'] + 1,
            'element' => array(
                'name' => 'img',
                'attributes' => array(
                    'src' => $link['element']['attributes']['href'],
                    'alt' => $link['element']['text'],
                ),
            ),
        );

        $inline['element']['attributes'] += $link['element']['attributes'];

        unset($inline['element']['attributes']['href']);

        return $inline;
    }

    protected function inlineLink($excerpt)
    {
        $element = array(
            'name' => 'a',
            'handler' => 'line',
            'text' => null,
            'attributes' => array(
                'href' => null,
                'title' => null,
            ),
        );

        $extent = 0;

        $remainder = $excerpt['text'];

        if (preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches))
        {
            $element['text'] = $matches[1];

            $extent += strlen($matches[0]);

            $remainder = substr($remainder, $extent);
        }
        else
        {
            return null;
        }

        if (preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*"|\'[^\']*\'))?\s*[)]/', $remainder, $matches))
        {
            $element['attributes']['href'] = $matches[1];

            if (isset($matches[2]))
            {
                $element['attributes']['title'] = substr($matches[2], 1, - 1);
            }

            $extent += strlen($matches[0]);
        }
        else
        {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches))
            {
                $definition = strlen($matches[1]) ? $matches[1] : $element['text'];
                $definition = strtolower($definition);

                $extent += strlen($matches[0]);
            }
            else
            {
                $definition = strtolower($element['text']);
            }

            if ( ! isset($this->definitionData['Reference'][$definition]))
            {
                return null;
            }

            $Definition = $this->definitionData['Reference'][$definition];

            $element['attributes']['href'] = $Definition['url'];
            $element['attributes']['title'] = $Definition['title'];
        }

        $element['attributes']['href'] = str_replace(array('&', '<'), array('&amp;', '&lt;'), $element['attributes']['href']);

        return array(
            'extent' => $extent,
            'element' => $element,
        );
    }

    protected function inlineMarkup($excerpt)
    {
        if ($this->markupEscaped or strpos($excerpt['text'], '>') === false)
        {
            return [];
        }

        if ($excerpt['text'][1] === '/' and preg_match('/^<\/\w*[ ]*>/s', $excerpt['text'], $matches))
        {
            return array(
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            );
        }

        if ($excerpt['text'][1] === '!' and preg_match('/^<!---?[^>-](?:-?[^-])*-->/s', $excerpt['text'], $matches))
        {
            return array(
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            );
        }

        if ($excerpt['text'][1] !== ' ' and preg_match('/^<\w*(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*\/?>/s', $excerpt['text'], $matches))
        {
            return array(
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            );
        }

        return [];
    }

    protected function inlineSpecialCharacter($excerpt)
    {
        if ($excerpt['text'][0] === '&' and ! preg_match('/^&#?\w+;/', $excerpt['text']))
        {
            return array(
                'markup' => '&amp;',
                'extent' => 1,
            );
        }

        $SpecialCharacter = array('>' => 'gt', '<' => 'lt', '"' => 'quot');

        if (isset($SpecialCharacter[$excerpt['text'][0]]))
        {
            return array(
                'markup' => '&'.$SpecialCharacter[$excerpt['text'][0]].';',
                'extent' => 1,
            );
        }

        return [];
    }

    protected function inlineStrikethrough($excerpt)
    {
        if ( ! isset($excerpt['text'][1]))
        {
            return [];
        }

        if ($excerpt['text'][1] === '~' and preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $excerpt['text'], $matches))
        {
            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'del',
                    'text' => $matches[1],
                    'handler' => 'line',
                ),
            );
        }

        return [];
    }

    protected function inlineUrl($excerpt)
    {
        if ($this->urlsLinked !== true or ! isset($excerpt['text'][2]) or $excerpt['text'][2] !== '/')
        {
            return [];
        }

        if (preg_match('/\bhttps?:[\/]{2}[^\s<]+\b\/*/ui', $excerpt['context'], $matches, PREG_OFFSET_CAPTURE))
        {
            return [
                'extent' => strlen($matches[0][0]),
                'position' => $matches[0][1],
                'element' => [
                    'name' => 'a',
                    'text' => $matches[0][0],
                    'attributes' => [
                        'href' => $matches[0][0],
                    ],
                ]
            ];
        }

        return [];
    }

    protected function inlineUrlTag($excerpt)
    {
        if (strpos($excerpt['text'], '>') !== false and preg_match('/^<(\w+:\/{2}[^ >]+)>/i', $excerpt['text'], $matches))
        {
            $url = str_replace(array('&', '<'), array('&amp;', '&lt;'), $matches[1]);

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => array(
                        'href' => $url,
                    ),
                ),
            );
        }

        return [];
    }

    protected function unmarkedText($text)
    {
        if ($this->breaksEnabled)
        {
            $text = preg_replace('/[ ]*\n/', "<br />\n", $text);
        }
        else
        {
            $text = preg_replace('/(?:[ ][ ]+|[ ]*\\\\)\n/', "<br />\n", $text);
            $text = str_replace(" \n", "\n", $text);
        }

        return $text;
    }

    protected function element(array $element)
    {
        $markup = '<'.$element['name'];

        if (isset($element['attributes']))
        {
            foreach ($element['attributes'] as $name => $value)
            {
                if ($value === null)
                {
                    continue;
                }

                $markup .= ' '.$name.'="'.$value.'"';
            }
        }

        if (isset($element['text']))
        {
            $markup .= '>';

            if (isset($element['handler']))
            {
                $markup .= $this->{$element['handler']}($element['text']);
            }
            else
            {
                $markup .= $element['text'];
            }

            $markup .= '</'.$element['name'].'>';
        }
        else
        {
            $markup .= ' />';
        }

        return $markup;
    }

    protected function elements(array $elements)
    {
        $markup = '';

        foreach ($elements as $element)
        {
            $markup .= "\n" . $this->element($element);
        }

        $markup .= "\n";

        return $markup;
    }

    protected function li($lines)
    {
        $markup = $this->lines($lines);

        $trimmedMarkup = trim($markup);

        if ( ! in_array('', $lines) and substr($trimmedMarkup, 0, 3) === '<p>')
        {
            $markup = $trimmedMarkup;
            $markup = substr($markup, 3);

            $position = strpos($markup, "</p>");

            $markup = substr_replace($markup, '', $position, 4);
        }

        return $markup;
    }

    function parse($text)
    {
        $markup = $this->text($text);

        return $markup;
    }

    static function instance($name = 'default')
    {
        if (isset(self::$instances[$name]))
        {
            return self::$instances[$name];
        }

        $instance = new static();

        self::$instances[$name] = $instance;

        return $instance;
    }

    private static $instances = array();

    protected $definitionData;

    protected $specialCharacters = array(
        '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!', '|',
    );

    protected $strongRegex = array(
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*_)+?)__(?!_)/us',
    );

    protected $emRegex = array(
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    );

    protected $regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*(?:\s*=\s*(?:[^"\'=<>`\s]+|"[^"]*"|\'[^\']*\'))?';

    protected $voidElements = array(
        'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source',
    );

    protected $textLevelElements = array(
        'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
        'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing',
        'i', 'rp', 'del', 'code',          'strike', 'marquee',
        'q', 'rt', 'ins', 'font',          'strong',
        's', 'tt', 'kbd', 'mark',
        'u', 'xm', 'sub', 'nobr',
                   'sup', 'ruby',
                   'var', 'span',
                   'wbr', 'time',
    );
}
