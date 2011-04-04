<?php
class Troll_Textile
{
	// define these before including this file to override the standard glyphs
	const TXT_QUOTE_SINGLE_OPEN  = '&#8216;';
	const TXT_QUOTE_SINGLE_CLOSE = '&#8217;';
	const TXT_QUOTE_DOUBLE_OPEN  = '&#8220;';
	const TXT_QUOTE_DOUBLE_CLOSE = '&#8221;';
	const TXT_APOSTROPHE         = '&#8217;';
	const TXT_PRIME              = '&#8242;';
	const TXT_PRIME_DOUBLE       = '&#8243;';
	const TXT_ELLIPSIS           = '&#8230;';
	const TXT_EMDASH             = '&#8212;';
	const TXT_ENDASH             = '&#8211;';
	const TXT_DIMENSION          = '&#215;';
	const TXT_TRADEMARK          = '&#8482;';
	const TXT_REGISTERED         = '&#174;';
	const TXT_COPYRIGHT          = '&#169;';
	
    public $hlgn;
    public $vlgn;
    public $clas;
    public $lnge;
    public $styl;
    public $cspn;
    public $rspn;
    public $a;
    public $s;
    public $c;
    public $pnct;
    public $rel;
    public $fn;
    
    public $shelf = array();
    public $restricted = false;
    public $noimage = false;
    public $lite = false;
    public $url_schemes = array();
    public $glyph = array();
    public $hu = '';
    
    public $ver = '2.0.0';
    public $rev = '$Rev: 216 $';
    
    public static function html($text)
    {
    	$textile = new self();
    	return $textile->TextileThis($text);
    }


    function __construct()
    {
        $this->hlgn = "(?:\<(?!>)|(?<!<)\>|\<\>|\=|[()]+(?! ))";
        $this->vlgn = "[\-^~]";
        $this->clas = "(?:\([^)]+\))";
        $this->lnge = "(?:\[[^]]+\])";
        $this->styl = "(?:\{[^}]+\})";
        $this->cspn = "(?:\\\\\d+)";
        $this->rspn = "(?:\/\d+)";
        $this->a = "(?:{$this->hlgn}|{$this->vlgn})*";
        $this->s = "(?:{$this->cspn}|{$this->rspn})*";
        $this->c = "(?:{$this->clas}|{$this->styl}|{$this->lnge}|{$this->hlgn})*";

        $this->pnct = '[\!"#\$%&\'()\*\+,\-\./:;<=>\?@\[\\\]\^_`{\|}\~]';
        $this->urlch = '[\w"$\-_.+!*\'(),";\/?:@=&%#{}|\\^~\[\]`]';

        $this->url_schemes = array('http','https','ftp','mailto');

        $this->btag = array('bq', 'bc', 'notextile', 'pre', 'h[1-6]', 'fn\d+', 'p');

        $this->glyph = array(
           'QUOTE_SINGLE_OPEN'  => self::TXT_QUOTE_SINGLE_OPEN,
           'QUOTE_SINGLE_CLOSE' => self::TXT_QUOTE_SINGLE_CLOSE,
           'QUOTE_DOUBLE_OPEN'  => self::TXT_QUOTE_DOUBLE_OPEN,
           'QUOTE_DOUBLE_CLOSE' => self::TXT_QUOTE_DOUBLE_CLOSE,
           'APOSTROPHE'         => self::TXT_APOSTROPHE,
           'PRIME'              => self::TXT_PRIME,
           'PRIME_DOUBLE'       => self::TXT_PRIME_DOUBLE,
           'ELLIPSIS'           => self::TXT_ELLIPSIS,
           'EMDASH'             => self::TXT_EMDASH,
           'ENDASH'             => self::TXT_ENDASH,
           'DIMENSION'          => self::TXT_DIMENSION,
           'TRADEMARK'          => self::TXT_TRADEMARK,
           'REGISTERED'         => self::TXT_REGISTERED,
           'COPYRIGHT'          => self::TXT_COPYRIGHT,
        );

        if (defined('hu'))
            $this->hu = hu;

    }


    function TextileThis($text, $lite='', $encode='', $noimage='', $strict='', $rel='')
    {
        if ($rel)
           $this->rel = ' rel="'.$rel.'" ';
        $this->lite = $lite;
        $this->noimage = $noimage;

        if ($encode) {
         $text = $this->incomingEntities($text);
            $text = str_replace("x%x%", "&#38;", $text);
            return $text;
        } else {

            if(!$strict) {
                $text = $this->cleanWhiteSpace($text);
            }

            $text = $this->getRefs($text);

            if (!$lite) {
                $text = $this->block($text);
            }

            $text = $this->retrieve($text);

                // just to be tidy
            $text = str_replace("<br />", "<br />\n", $text);

            return $text;
        }
    }


    function TextileRestricted($text, $lite=1, $noimage=1, $rel='nofollow')
    {
        $this->restricted = true;
        $this->lite = $lite;
        $this->noimage = $noimage;
        if ($rel)
           $this->rel = ' rel="'.$rel.'" ';

            // escape any raw html
            $text = $this->encode_html($text, 0);

            $text = $this->cleanWhiteSpace($text);
            $text = $this->getRefs($text);

            if ($lite) {
                $text = $this->blockLite($text);
            }
            else {
                $text = $this->block($text);
            }

            $text = $this->retrieve($text);

                // just to be tidy
            $text = str_replace("<br />", "<br />\n", $text);

            return $text;
    }


    function pba($in, $element = "") // "parse block attributes"
    {
        $style = '';
        $class = '';
        $lang = '';
        $colspan = '';
        $rowspan = '';
        $id = '';
        $atts = '';

        if (!empty($in)) {
            $matched = $in;
            if ($element == 'td') {
                if (preg_match("/\\\\(\d+)/", $matched, $csp)) $colspan = $csp[1];
                if (preg_match("/\/(\d+)/", $matched, $rsp)) $rowspan = $rsp[1];
            }

            if ($element == 'td' or $element == 'tr') {
                if (preg_match("/($this->vlgn)/", $matched, $vert))
                    $style[] = "vertical-align:" . $this->vAlign($vert[1]) . ";";
            }

            if (preg_match("/\{([^}]*)\}/", $matched, $sty)) {
                $style[] = rtrim($sty[1], ';') . ';';
                $matched = str_replace($sty[0], '', $matched);
            }

            if (preg_match("/\[([^]]+)\]/U", $matched, $lng)) {
                $lang = $lng[1];
                $matched = str_replace($lng[0], '', $matched);
            }

            if (preg_match("/\(([^()]+)\)/U", $matched, $cls)) {
                $class = $cls[1];
                $matched = str_replace($cls[0], '', $matched);
            }

            if (preg_match("/([(]+)/", $matched, $pl)) {
                $style[] = "padding-left:" . strlen($pl[1]) . "em;";
                $matched = str_replace($pl[0], '', $matched);
            }

            if (preg_match("/([)]+)/", $matched, $pr)) {
                $style[] = "padding-right:" . strlen($pr[1]) . "em;";
                $matched = str_replace($pr[0], '', $matched);
            }

            if (preg_match("/($this->hlgn)/", $matched, $horiz))
                $style[] = "text-align:" . $this->hAlign($horiz[1]) . ";";

            if (preg_match("/^(.*)#(.*)$/", $class, $ids)) {
                $id = $ids[2];
                $class = $ids[1];
            }

            if ($this->restricted)
                return ($lang)    ? ' lang="'    . $lang            .'"':'';

            return join('',array(
                ($style)   ? ' style="'   . join("", $style) .'"':'',
                ($class)   ? ' class="'   . $class           .'"':'',
                ($lang)    ? ' lang="'    . $lang            .'"':'',
                ($id)      ? ' id="'      . $id              .'"':'',
                ($colspan) ? ' colspan="' . $colspan         .'"':'',
                ($rowspan) ? ' rowspan="' . $rowspan         .'"':''
            ));
        }
        return '';
    }


    function hasRawText($text)
    {
        // checks whether the text has text not already enclosed by a block tag
        $r = trim(preg_replace('@<(p|blockquote|div|form|table|ul|ol|pre|h\d)[^>]*?>.*</\1>@s', '', trim($text)));
        $r = trim(preg_replace('@<(hr|br)[^>]*?/>@', '', $r));
        return '' != $r;
    }


    function table($text)
    {
        $text = $text . "\n\n";
        return preg_replace_callback("/^(?:table(_?{$this->s}{$this->a}{$this->c})\. ?\n)?^({$this->a}{$this->c}\.? ?\|.*\|)\n\n/smU",
           array(&$this, "fTable"), $text);
    }


    function fTable($matches)
    {
        $tatts = $this->pba($matches[1], 'table');

        foreach(preg_split("/\|$/m", $matches[2], -1, PREG_SPLIT_NO_EMPTY) as $row) {
            if (preg_match("/^($this->a$this->c\. )(.*)/m", ltrim($row), $rmtch)) {
                $ratts = $this->pba($rmtch[1], 'tr');
                $row = $rmtch[2];
            } else $ratts = '';

                $cells = array();
            foreach(explode("|", $row) as $cell) {
                $ctyp = "d";
                if (preg_match("/^_/", $cell)) $ctyp = "h";
                if (preg_match("/^(_?$this->s$this->a$this->c\. )(.*)/", $cell, $cmtch)) {
                    $catts = $this->pba($cmtch[1], 'td');
                    $cell = $cmtch[2];
                } else $catts = '';

                $cell = $this->graf($this->span($cell));

                if (trim($cell) != '')
                    $cells[] = "\t\t\t<t$ctyp$catts>$cell</t$ctyp>";
            }
            $rows[] = "\t\t<tr$ratts>\n" . join("\n", $cells) . ($cells ? "\n" : "") . "\t\t</tr>";
            unset($cells, $catts);
        }
        return "\t<table$tatts>\n" . join("\n", $rows) . "\n\t</table>\n\n";
    }


    function lists($text)
    {
        return preg_replace_callback("/^([#*]+$this->c .*)$(?![^#*])/smU", array(&$this, "fList"), $text);
    }


    function fList($m)
    {
        $text = explode("\n", $m[0]);
        foreach($text as $line) {
            $nextline = next($text);
            if (preg_match("/^([#*]+)($this->a$this->c) (.*)$/s", $line, $m)) {
                list(, $tl, $atts, $content) = $m;
                $nl = '';
                if (preg_match("/^([#*]+)\s.*/", $nextline, $nm))
                	$nl = $nm[1];
                if (!isset($lists[$tl])) {
                    $lists[$tl] = true;
                    $atts = $this->pba($atts);
                    $line = "\t<" . $this->lT($tl) . "l$atts>\n\t\t<li>" . $this->graf($content);
                } else {
                    $line = "\t\t<li>" . $this->graf($content);
                }

                if(strlen($nl) <= strlen($tl)) $line .= "</li>";
                foreach(array_reverse($lists) as $k => $v) {
                    if(strlen($k) > strlen($nl)) {
                        $line .= "\n\t</" . $this->lT($k) . "l>";
                        if(strlen($k) > 1)
                            $line .= "</li>";
                        unset($lists[$k]);
                    }
                }
            }
            $out[] = $line;
        }
        return join("\n", $out);
    }


    function lT($in)
    {
        return preg_match("/^#+/", $in) ? 'o' : 'u';
    }


    function doPBr($in)
    {
        return preg_replace_callback('@<(p)([^>]*?)>(.*)(</\1>)@s', array(&$this, 'doBr'), $in);
    }


    function doBr($m)
    {
        $content = preg_replace("@(.+)(?<!<br>|<br />)\n(?![#*\s|])@", '$1<br />', $m[3]);
        return '<'.$m[1].$m[2].'>'.$content.$m[4];
    }


    function block($text)
    {
        $find = $this->btag;
        $tre = join('|', $find);

        $text = explode("\n\n", $text);

        $tag = 'p';
        $atts = $cite = $graf = $ext  = '';

        foreach($text as $line) {
            $anon = 0;
            if (preg_match("/^($tre)($this->a$this->c)\.(\.?)(?::(\S+))? (.*)$/s", $line, $m)) {
                // last block was extended, so close it
                if ($ext)
                    $out[count($out)-1] .= $c1;
                // new block
                list(,$tag,$atts,$ext,$cite,$graf) = $m;
                list($o1, $o2, $content, $c2, $c1) = $this->fBlock(array(0,$tag,$atts,$ext,$cite,$graf));

                // leave off c1 if this block is extended, we'll close it at the start of the next block
                if ($ext)
                    $line = $o1.$o2.$content.$c2;
                else
                    $line = $o1.$o2.$content.$c2.$c1;
            }
            else {
                // anonymous block
                $anon = 1;
                if ($ext or !preg_match('/^ /', $line)) {
                    list($o1, $o2, $content, $c2, $c1) = $this->fBlock(array(0,$tag,$atts,$ext,$cite,$line));
                    // skip $o1/$c1 because this is part of a continuing extended block
                    if ($tag == 'p' and !$this->hasRawText($content)) {
                        $line = $content;
                    }
                    else {
                        $line = $o2.$content.$c2;
                    }
                }
                else {
                   $line = $this->graf($line);
                }
            }

            $line = $this->doPBr($line);
            $line = preg_replace('/<br>/', '<br />', $line);

            if ($ext and $anon)
                $out[count($out)-1] .= "\n".$line;
            else
                $out[] = $line;

            if (!$ext) {
                $tag = 'p';
                $atts = '';
                $cite = '';
                $graf = '';
            }
        }
        if ($ext) $out[count($out)-1] .= $c1;
        return join("\n\n", $out);
    }




    function fBlock($m)
    {
        list(, $tag, $atts, $ext, $cite, $content) = $m;
        $atts = $this->pba($atts);

        $o1 = $o2 = $c2 = $c1 = '';

        if (preg_match("/fn(\d+)/", $tag, $fns)) {
            $tag = 'p';
            $fnid = empty($this->fn[$fns[1]]) ? $fns[1] : $this->fn[$fns[1]];
            $atts .= ' id="fn' . $fnid . '"';
            if (strpos($atts, 'class=') === false)
                $atts .= ' class="footnote"';
            $content = '<sup>' . $fns[1] . '</sup> ' . $content;
        }

        if ($tag == "bq") {
            $cite = $this->checkRefs($cite);
            $cite = ($cite != '') ? ' cite="' . $cite . '"' : '';
            $o1 = "\t<blockquote$cite$atts>\n";
            $o2 = "\t\t<p$atts>";
            $c2 = "</p>";
            $c1 = "\n\t</blockquote>";
        }
        elseif ($tag == 'bc') {
            $o1 = "<pre$atts>";
            $o2 = "<code$atts>";
            $c2 = "</code>";
            $c1 = "</pre>";
            $content = $this->shelve($this->encode_html(rtrim($content, "\n")."\n"));
        }
        elseif ($tag == 'notextile') {
            $content = $this->shelve($content);
            $o1 = $o2 = '';
            $c1 = $c2 = '';
        }
        elseif ($tag == 'pre') {
            $content = $this->shelve($this->encode_html(rtrim($content, "\n")."\n"));
            $o1 = "<pre$atts>";
            $o2 = $c2 = '';
            $c1 = "</pre>";
        }
        else {
            $o2 = "\t<$tag$atts>";
            $c2 = "</$tag>";
          }

        $content = $this->graf($content);

        return array($o1, $o2, $content, $c2, $c1);
    }


    function graf($text)
    {
        // handle normal paragraph text
        if (!$this->lite) {
            $text = $this->noTextile($text);
            $text = $this->code($text);
        }

        $text = $this->links($text);
        if (!$this->noimage)
            $text = $this->image($text);

        if (!$this->lite) {
            $text = $this->lists($text);
            $text = $this->table($text);
        }

        $text = $this->span($text);
        $text = $this->footnoteRef($text);
        $text = $this->glyphs($text);
        return rtrim($text, "\n");
    }


    function span($text)
    {
        $qtags = array('\*\*','\*','\?\?','-','__','_','%','\+','~','\^');
        $pnct = ".,\"'?!;:";

        foreach($qtags as $f) {
            $text = preg_replace_callback("/
                (?:^|(?<=[\s>$pnct])|([{[]))
                ($f)(?!$f)
                ({$this->c})
                (?::(\S+))?
                ([^\s$f]+|\S[^$f\n]*[^\s$f\n])
                ([$pnct]*)
                $f
                (?:$|([\]}])|(?=[[:punct:]]{1,2}|\s))
            /x", array(&$this, "fSpan"), $text);
        }
        return $text;
    }


    function fSpan($m)
    {
        $qtags = array(
            '*'  => 'strong',
            '**' => 'b',
            '??' => 'cite',
            '_'  => 'em',
            '__' => 'i',
            '-'  => 'del',
            '%'  => 'span',
            '+'  => 'ins',
            '~'  => 'sub',
            '^'  => 'sup',
        );

        list(,, $tag, $atts, $cite, $content, $end) = $m;
        $tag = $qtags[$tag];
        $atts = $this->pba($atts);
        $atts .= ($cite != '') ? 'cite="' . $cite . '"' : '';

        $out = "<$tag$atts>$content$end</$tag>";

        return $out;

    }


    function links($text)
    {
        return preg_replace_callback('/
            (?:^|(?<=[\s>.$pnct\(])|([{[])) # $pre
            "                            # start
            (' . $this->c . ')           # $atts
            ([^"]+)                      # $text
            \s?
            (?:\(([^)]+)\)(?="))?        # $title
            ":
            ('.$this->urlch.'+)          # $url
            (\/)?                        # $slash
            ([^\w\/;]*)                  # $post
            (?:([\]}])|(?=\s|$|\)))
        /Ux', array(&$this, "fLink"), $text);
    }


    function fLink($m)
    {
        list(, $pre, $atts, $text, $title, $url, $slash, $post) = $m;

        $url = $this->checkRefs($url);

        $atts = $this->pba($atts);
        $atts .= ($title != '') ? ' title="' . $this->encode_html($title) . '"' : '';

        if (!$this->noimage)
            $text = $this->image($text);

        $text = $this->span($text);
        $text = $this->glyphs($text);

        $url = $this->relURL($url);

        $out = '<a href="' . $this->encode_html($url . $slash) . '"' . $atts . $this->rel . '>' . $text . '</a>' . $post;

        return $this->shelve($out);

    }


    function getRefs($text)
    {
        return preg_replace_callback("/(?<=^|\s)\[(.+)\]((?:http:\/\/|\/)\S+)(?=\s|$)/U",
            array(&$this, "refs"), $text);
    }


    function refs($m)
    {
        list(, $flag, $url) = $m;
        $this->urlrefs[$flag] = $url;
        return '';
    }


    function checkRefs($text)
    {
        return (isset($this->urlrefs[$text])) ? $this->urlrefs[$text] : $text;
    }


    function relURL($url)
    {
        $parts = parse_url($url);
        if ((empty($parts['scheme']) or @$parts['scheme'] == 'http') and
             empty($parts['host']) and
             preg_match('/^\w/', @$parts['path']))
            $url = $this->hu.$url;
        if ($this->restricted and !empty($parts['scheme']) and
              !in_array($parts['scheme'], $this->url_schemes))
            return '#';
        return $url;
    }


    function image($text)
    {
        return preg_replace_callback("/
            (?:[[{])?          # pre
            \!                 # opening !
            (\<|\=|\>)??       # optional alignment atts
            ($this->c)         # optional style,class atts
            (?:\. )?           # optional dot-space
            ([^\s(!]+)         # presume this is the src
            \s?                # optional space
            (?:\(([^\)]+)\))?  # optional title
            \!                 # closing
            (?::(\S+))?        # optional href
            (?:[\]}]|(?=\s|$)) # lookahead: space or end of string
        /Ux", array(&$this, "fImage"), $text);
    }


    function fImage($m)
    {
        list(, $algn, $atts, $url) = $m;
        $atts  = $this->pba($atts);
        $atts .= ($algn != '')  ? ' align="' . $this->iAlign($algn) . '"' : '';
        $atts .= (isset($m[4])) ? ' title="' . $m[4] . '"' : '';
        $atts .= (isset($m[4])) ? ' alt="'   . $m[4] . '"' : ' alt=""';
        $size = @getimagesize($url);
        if ($size) $atts .= " $size[3]";

        $href = (isset($m[5])) ? $this->checkRefs($m[5]) : '';
        $url = $this->checkRefs($url);

        $url = $this->relURL($url);

        $out = array(
            ($href) ? '<a href="' . $href . '">' : '',
            '<img src="' . $url . '"' . $atts . ' />',
            ($href) ? '</a>' : ''
        );

        return join('',$out);
    }


    function code($text)
    {
        $text = $this->doSpecial($text, '<code>', '</code>', 'fCode');
        $text = $this->doSpecial($text, '@', '@', 'fCode');
        $text = $this->doSpecial($text, '<pre>', '</pre>', 'fPre');
        return $text;
    }


    function fCode($m)
    {
      @list(, $before, $text, $after) = $m;
      if ($this->restricted)
          // $text is already escaped
            return $before.$this->shelve('<code>'.$text.'</code>').$after;
      else
            return $before.$this->shelve('<code>'.$this->encode_html($text).'</code>').$after;
    }


    function fPre($m)
    {
      @list(, $before, $text, $after) = $m;
      if ($this->restricted)
          // $text is already escaped
            return $before.'<pre>'.$this->shelve($text).'</pre>'.$after;
      else
            return $before.'<pre>'.$this->shelve($this->encode_html($text)).'</pre>'.$after;
    }

    function shelve($val)
    {
        $i = uniqid(rand());
        $this->shelf[$i] = $val;
        return $i;
    }


    function retrieve($text)
    {
        if (is_array($this->shelf))
            do {
                $old = $text;
                $text = strtr($text, $this->shelf);
             } while ($text != $old);

        return $text;
    }

    function incomingEntities($text)
    {
        return preg_replace("/&(?![#a-z0-9]+;)/i", "x%x%", $text);
    }

    function cleanWhiteSpace($text)
    {
        $out = str_replace("\r\n", "\n", $text);
        $out = preg_replace("/\n{3,}/", "\n\n", $out);
        $out = preg_replace("/\n *\n/", "\n\n", $out);
        $out = preg_replace('/"$/', "\" ", $out);
        return $out;
    }


    function doSpecial($text, $start, $end, $method='fSpecial')
    {
      return preg_replace_callback('/(^|\s|[[({>])'.preg_quote($start, '/').'(.*?)'.preg_quote($end, '/').'(\s|$|[\])}])?/ms',
            array(&$this, $method), $text);
    }

    function fSpecial($m)
    {
        // A special block like notextile or code
      @list(, $before, $text, $after) = $m;
        return $before.$this->shelve($this->encode_html($text)).$after;
    }

    function noTextile($text)
    {
         $text = $this->doSpecial($text, '<notextile>', '</notextile>', 'fTextile');
         return $this->doSpecial($text, '==', '==', 'fTextile');

    }

    function fTextile($m)
    {
        @list(, $before, $notextile, $after) = $m;
        #$notextile = str_replace(array_keys($modifiers), array_values($modifiers), $notextile);
        return $before.$this->shelve($notextile).$after;
    }

    function footnoteRef($text)
    {
        return preg_replace('/\b\[([0-9]+)\](\s)?/Ue',
            '$this->footnoteID(\'\1\',\'\2\')', $text);
    }

    function footnoteID($id, $t)
    {
        if (empty($this->fn[$id]))
            $this->fn[$id] = uniqid(rand());
        $fnid = $this->fn[$id];
        return '<sup class="footnote"><a href="#fn'.$fnid.'">'.$id.'</a></sup>'.$t;
    }

    function glyphs($text)
    {
        // fix: hackish
        $text = preg_replace('/"\z/', "\" ", $text);
        $pnc = '[[:punct:]]';

        $glyph_search = array(
            '/(\w)\'(\w)/',                                      // APOSTROPHE's
            '/(\s)\'(\d+\w?)\b(?!\')/',                          // back in '88
            '/(\S)\'(?=\s|'.$pnc.'|<|$)/',                       //  single closing
            '/\'/',                                              //  single opening
            '/(\S)\"(?=\s|'.$pnc.'|<|$)/',                       //  double closing
            '/"/',                                               //  double opening
            '/\b([A-Z][A-Z0-9]{2,})\b(?:[(]([^)]*)[)])/',        //  3+ uppercase acronym
            '/\b([A-Z][A-Z\'\-]+[A-Z])(?=[\s.,\)>])/',           //  3+ uppercase
            '/\b( )?\.{3}/',                                     //  ELLIPSIS
            '/(\s?)--(\s?)/',                                    //  em dash
            '/\s-(?:\s|$)/',                                     //  en dash
            '/(\d+)( ?)x( ?)(?=\d+)/',                           //  DIMENSION sign
            '/\b ?[([]TM[])]/i',                                 //  TRADEMARK
            '/\b ?[([]R[])]/i',                                  //  REGISTERED
            '/\b ?[([]C[])]/i',                                  //  COPYRIGHT
         );

        extract($this->glyph, EXTR_PREFIX_ALL, 'TXT');
        
        $glyph_replace = array(
            '$1'.$TXT_APOSTROPHE.'$2',           // APOSTROPHE's
            '$1'.$TXT_APOSTROPHE.'$2',           // back in '88
            '$1'.$TXT_QUOTE_SINGLE_CLOSE,        //  single closing
            $TXT_QUOTE_SINGLE_OPEN,              //  single opening
            '$1'.$TXT_QUOTE_DOUBLE_CLOSE,        //  double closing
            $TXT_QUOTE_DOUBLE_OPEN,              //  double opening
            '<acronym title="$2">$1</acronym>',  //  3+ uppercase acronym
            '<span class="caps">$1</span>',      //  3+ uppercase
            '$1'.$TXT_ELLIPSIS,                  //  ELLIPSIS
            '$1'.$TXT_EMDASH.'$2',               //  em dash
            ' '.$TXT_ENDASH.' ',                 //  en dash
            '$1$2'.$TXT_DIMENSION.'$3',          //  DIMENSION sign
            $TXT_TRADEMARK,                      //  TRADEMARK
            $TXT_REGISTERED,                     //  REGISTERED
            $TXT_COPYRIGHT,                      //  COPYRIGHT
         );

         $text = preg_split("/(<.*>)/U", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
         foreach($text as $line) {
             if (!preg_match("/<.*>/", $line)) {
                 $line = preg_replace($glyph_search, $glyph_replace, $line);
             }
              $glyph_out[] = $line;
         }
         return join('', $glyph_out);
    }

    function iAlign($in)
    {
        $vals = array(
            '<' => 'left',
            '=' => 'center',
            '>' => 'right');
        return (isset($vals[$in])) ? $vals[$in] : '';
    }

    function hAlign($in)
    {
        $vals = array(
            '<'  => 'left',
            '='  => 'center',
            '>'  => 'right',
            '<>' => 'justify');
        return (isset($vals[$in])) ? $vals[$in] : '';
    }

    function vAlign($in)
    {
        $vals = array(
            '^' => 'top',
            '-' => 'middle',
            '~' => 'bottom');
        return (isset($vals[$in])) ? $vals[$in] : '';
    }

    function encode_html($str, $quotes=1)
    {
        $a = array(
            '&' => '&#38;',
            '<' => '&#60;',
            '>' => '&#62;',
        );
        if ($quotes) $a = $a + array(
            "'" => '&#39;',
            '"' => '&#34;',
        );

        return strtr($str, $a);
    }

    function textile_popup_help($name, $helpvar, $windowW, $windowH)
    {
        return ' <a target="_blank" href="http://www.textpattern.com/help/?item=' . $helpvar . '" onclick="window.open(this.href, \'popupwindow\', \'width=' . $windowW . ',height=' . $windowH . ',scrollbars,resizable\'); return false;">' . $name . '</a><br />';

        return $out;
    }

    function blockLite($text)
    {
        $this->btag = array('bq', 'p');
        return $this->block($text."\n\n");
    }
}