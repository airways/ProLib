<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package CELL CMS
 * @author Isaac Raway (MetaSushi, LLC) <isaac@metasushi.com>
 *
 * Copyright (c)2009, 2010, 2011, 2012. Isaac Raway and MetaSushi, LLC.
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Isaac Raway and
 * MetaSushi, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 *
 **/

define('NODE_TEXT', 1);
define('NODE_TAG', 2);
define('NODE_VAR', 3);

define('EXP_VAL', 1);
define('EXP_OP', 2);
define('EXP_VAR', 3);
define('EXP_FUNC', 4);

class PL_Template {
    var $modules = array();
    var $fieldtypes = array();
    var $tabs = array();
    var $cache = array();
    var $taglibs = array();
    var $taglib_prefix = 'ce';
    var $globals = array();
    var $preserve_empty_vars = FALSE;

    function __construct()
    {
        global $PROLIB;
        $this->prolib = &$PROLIB;
        $this->CI = &get_instance();
        $this->builtins = new PL_TemplateBuiltins();


        // give drivers a chance to override globals or otherwise change the template environment
        $this->prolib->pl_drivers->template_init($this);
    }

    function register_taglib($name, $taglib)
    {
        $this->taglibs[$name] = $taglib;
    }

    function parse($template, &$i = 0, $parsing_tag_name = FALSE)
    {
        $root = $i == 0;
        if($root)
        {

            // check cache
            $hash = md5($template);
            if(isset($this->cache[$hash]))
            {
                return $this->cache[$hash];
            }
        }

        $result = new PL_TemplateNode(NODE_TAG);

        $state = 0;
        $text = '';
        $block = 0;
        $depth = 0;

        for($i = $i; $i < strlen($template); $i++)
        {
            switch($state)
            {
                case 0:     // plain text - either at root of template or inside a {pair}...{/pair}
                    $c = $template[$i];
                    $next_c = substr($template, $i+1, 1);
                    switch($c)
                    {
                        case '{':
                            if(strlen($text) > 0)
                                $result->blocks[$block][] = new PL_TemplateNode(NODE_TEXT, $text);
                            $text = '';
                            $state = 1;
                            $depth++;
                            break;
                        default:
                            $text .= $c;
                            break;

                    }
                    break;
                case 1:     // inside a {var} or opening {tag}
                    $c = $template[$i];
                    switch($c)
                    {
                        case '{':
                            $depth++;
                            $text .= $c;
                            break;
                        case '}':
                            $depth--;
                            if($depth == 0)
                            {
                                // figure out if this is a pair or a single var
                                $check = trim($text);
                                // find the first word up to white space
                                preg_match('/([^\s]*+)(.*)/s', $check, $matches);
    
                                $tag_text = strtolower($matches[1]);
                                $tag_name = strtolower($matches[1]);
                                $params = $matches[2];
    
                                $builtin = FALSE;
                                $new_block = FALSE;
    
                                if(strpos($tag_name, ':') !== FALSE)
                                {
                                    $taglib = explode(':', $tag_name);
                                    if($taglib[0] == $parsing_tag_name)
                                    {
                                        // additional block within the tag
                                        $block ++;
                                        $result->block_names[$block] = $taglib[1];
                                        $new_block = TRUE;
                                    } else {
                                        if($this->taglib_prefix)
                                        {
                                            // when the prefix is set, taglib references are only processed when the
                                            // tag starts with this prefix
                                            if($taglib[0] == $this->taglib_prefix)
                                            {
                                                array_shift($taglib);
                                                $tag_name = $taglib[1];
                                                $taglib = $taglib[0];
                                            } else {
                                                // collapse the tagname back from it's parts
                                                $tag_name = implode(':', $taglib);
                                                $taglib = FALSE;
                                            }
                                        } else {
                                            $tag_name = $taglib[1];
                                            $taglib = $taglib[0];
                                        }
                                    }
                                } else {
                                    $taglib = FALSE;
                                    if(method_exists($this->builtins, '_'.$tag_name))
                                    {
                                        $builtin = TRUE;
                                    }
                                }
    
                                if(!$new_block)
                                {
                                    if(stripos($template, '{/'.$tag_text.'}', $i) > 0)
                                    {
                                        // pair tag - start parsing it's contents
                                        $i ++;
                                        $node = $this->parse($template, $i, $tag_text);
                                        $node->text = $tag_name;
                                        $node->taglib = $taglib;
                                        $node->params = $this->tokenize($params);
                                        $node->builtin = $builtin;
                                        $result->blocks[$block][] = $node;
                                    } elseif($tag_name[0] == '/') {
                                        if($text != '/'.$parsing_tag_name)
                                        {
                                            show_error('Syntax error - encountered closing {'.$text.'} tag, expecting '
                                                .($parsing_tag_name != ''
                                                    ? '{/'.$parsing_tag_name.'}'
                                                    : ' end of template'));
                                        } else {
                                            // done parsing this tag, we can return our result
                                            return $result;
                                        }
                                    } else {
                                        // single var
                                        $node = new PL_TemplateNode(NODE_VAR, $tag_name);
                                        $node->taglib = $taglib;
                                        $node->params = $this->tokenize($params);
                                        $node->builtin = $builtin;
                                        $result->blocks[$block][] = $node;
                                    }
                                }

                                $text = '';
                                $state = 0;
                            
                            } else {
                                $text .= $c;
                            }
                            break;
                        default:
                            $text .= $c;
                            break;

                    }
                    
                    break;
                default:
                    show_error('Bad state: '.$state);
            }
        }

        // add any trailing text
        if(strlen($text) > 0)
        {
            $result->blocks[$block][] = new PL_TemplateNode(NODE_TEXT, $text);
        }

        if($root)
        {
            // set cache
            $hash = md5($template);
            $this->cache[$hash] = $result;
            $result->hash = $hash;
        }

        return $result;
    }

    function load($template_name)
    {
        if(isset($this->cache[$template_name]))
        {
            return $this->cache[$template_name];
        } else {
            if(isset($this->template_root_dir))
            {
                if(file_exists($this->template_root_dir.$template_name.'.php'))
                {
                    $code = file_get_contents($this->template_root_dir.$template_name.'.php');
                } else {
                    show_error('Registered template_root_dir does not contain '.$template_name.'.php'); 
                }
            } else {
                $code = $this->CI->load->view($template_name, '', true);
            }
            $template = $this->parse($code);
            $this->cache[$template_name] = $template;
            return $template;
        }

    }

    function numeric_keys($array)
    {
        foreach(array_keys($array) as $k)
        {
            if(!is_numeric($k))
            {
                return FALSE;
            }
        }
        return TRUE;
    }

    function view($template, $dataset = array(array()), $block = 0)
    {
        //var_dump($dataset);
        echo $this->apply($template, $dataset, $block);
    }

    function apply($template, $dataset = array(array()), $block = 0, $base_data = array())
    {
        // applies a template to an array of data

        if(!is_array($dataset) || !$this->numeric_keys($dataset))
        {
            show_error('pl_template->apply() can only be called with an array of array rows.');
        }

        $result = '';

        if(is_string($template))
        {
            // string is a template filename
            $template = $this->load($template);
        } else {
            if(!($template instanceof PL_TemplateNode))
            {
                var_dump($template);
                show_error('pl_template->apply() called with invalid object - pass a filename or PL_TemplateNode object');
            }
        }

        foreach($dataset as $data)
        {
            if(is_object($data))
            {
                $data = $this->to_array($data);
            }

            $data = array_merge($this->globals, $base_data, $data);

            // convert all keys to lowercase
            foreach($data as $k => $v)
            {
                if($k != strtolower($k))
                {
                    $data[strtolower($k)] = $v;
                    unset($data[$k]);
                }
            }

            foreach($template->blocks[$block] as $node)
            {
                switch($node->type)
                {
                    case NODE_TEXT:
                        $result .= $node->text;
                        break;
                    case NODE_VAR:
                        $val = '';
                        if($this->lookup_var($node->text, $data, $val))
                        {
                            if(is_object($val) || is_array($val))
                            {
                                show_error('Treating data as a single var, but data element is an array or object: '.$node->text);
                            } else {
                                $result .= $val;
                            }
                        } else {
                            $found = FALSE;
                            
                            if(isset($this->taglibs[$node->taglib]))
                            {
                                if(method_exists($this->taglibs[$node->taglib], 'tag_'.$node->text))
                                {
                                    $rpn = $this->parse_exp($node->params);
                                    $params = $data;
                                    /*echo "<b>call tag</b> ".$node->taglib.":".$node->text."<br/>";
                                    echo "params:<br/>";
                                    var_dump($params);
                                    echo "data:<br/>";
                                    var_dump($data);
                                    echo "merged:<br/>";
                                    var_dump(array_merge($data, $params));*/

                                    // $params will be modified by assignments in the expression
                                    $this->eval_exp($rpn, $params, TRUE);
                                    $node->tmp_params = $params;
                                    $result .= call_user_func(array($this->taglibs[$node->taglib], 'tag_'.$node->text), $node);

                                    $found = TRUE;
                                }
                            } else {
                                if($this->preserve_empty_vars)
                                {
                                    $result .= "{".$node->text."}";
                                }
                            }

                            if(!$found && $node->taglib)
                            {
                                // we don't print errors if the reference didn't have a taglib part
                                show_error('Invalid single tag encountered: '.$node->taglib.':'.$node->text);
                            }
                        }
                        break;
                    case NODE_TAG:
                        // check for defined data index for this tag
                        if($node->builtin)
                        {
                            $result .= $this->builtins->{'_'.$node->text}($node, $data);
                        } elseif(isset($data[$node->text]))
                        {
                            if(!is_array($data[$node->text]) && !is_object($data[$node->text]))
                            {
                                show_error('Treating data as tag pair, but data element is not an array or object: '.$node->text);
                            }

                            $result .= $this->apply($node, $data[$node->text], 0, $data);
                        } else {
                            // check for taglibs implementing the tag
                            $found = FALSE;
                            if(isset($this->taglibs[$node->taglib]))
                            {
                                if(method_exists($this->taglibs[$node->taglib], 'pair_'.$node->text))
                                {
                                    $rpn = $this->parse_exp($node->params);
                                    $params = $data;
                                    // $params will be modified by assignments in the expression
                                    $this->eval_exp($rpn, $params, TRUE);
                                    $node->tmp_params = $params;
                                    $result .= call_user_func(array($this->taglibs[$node->taglib], 'pair_'.$node->text), $node);
                                    $found = TRUE;
                                }
                            }

                            if(!$found && $node->taglib)
                            {
                                // we don't print errors if the reference didn't have a taglib part
                                var_dump($this->taglibs);
                                show_error('Invalid tag pair encountered: '.$node->taglib.':'.$node->text);
                            }
                        }

                        break;
                    default:
                        show_error('Invalid node while applying template: '.$node->type);
                }
            }
        }

        return $this->_strip_blank_lines($result);
    }

    function _strip_blank_lines($string)
    {
        return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $string);
    }
    
    function lookup_var($var, &$data, &$val)
    {
        // split into variable segments separated by periods - 
        // these index either objects or arrays, and each
        // segment can be a string name or a numeric index
        $var_segments = explode('.', $var);
        
        // start with the current data structure array
        $data_node = &$data;
        
        $found = FALSE;
        
        foreach($var_segments as $i => $seg)
        {
            if(preg_match('/^\{(.*?)\}$/', $seg, $match))
            {
                $this->lookup_var($match[1], $data, $seg);
                $var_segments[$i] = $seg;
            }
        }
        // walk over the variable segments, digging into each
        // layer of the data structure if we find it
        /*if(count($var_segments) > 1)
        {
            var_dump($var_segments);
            var_dump($data_node);
        }
        */
        foreach($var_segments as $i => $seg)
        {
            /*if(count($var_segments) > 1)
            {
                echo 'isset($data_node['.$seg.']) = ' . isset($data_node[$seg]).'<br/>';
                
            }*/
            if(isset($data_node[$seg]))
            {
                if($i < count($var_segments)-1)
                {
                    if(!is_object($data_node[$seg]) && !is_array($data_node[$seg]))
                    {
                        show_error('Segment '.$i.' is not an object or array: '.$var);
                    } else {
                        // dig deeper
                        $data_node = &$data_node[$seg];
                        /*echo "found $seg:";
                        var_dump($data_node);*/
                    }
                } else {
                    // found it, let's get out of here
                    $val = $data_node[$seg];
                    return TRUE;
                }
            }
        }
        
        return FALSE;
    }

    static $precedence = array(
        '!' => 4,
        '*' => 3,
        '/' => 3,
        '%' => 3,
        '>' => 3,
        '<' => 3,
        '+' => 2,
        '-' => 2,
        '.' => 2,
        '=' => 1,
        '==' => 1,
        '!=' => 1,
        '>=' => 1,
        '<=' => 1,
        '&' => 1,
        '&&' => 1,
        '|' => 0,
        '||' => 0,
        '(' => -1,
        ')' => -1,
        ',' => -1,
        ' ' => -1,
    );

    static $left_assoc = array(
        '*' => TRUE,    // left to right
        '/' => TRUE,    // "
        '%' => TRUE,    // "
        '+' => TRUE,    // "
        '-' => TRUE,    // "
        '.' => TRUE,    // "
        '>' => TRUE,    // "
        '<' => TRUE,    // "
        '&' => TRUE,    // "
        '&&' => TRUE,    // "
        '|' => TRUE,    // "
        '||' => TRUE,    // "
        '=' => FALSE,   // right to left
        '==' => FALSE,  // "
        '!=' => FALSE,  // "
        '>=' => FALSE,  // "
        '<=' => FALSE,  // "
        '!' => FALSE,   // "
        //' ' => FALSE,   // "
    );

    function tokenize($exp)
    {
        $tokens = array();
        $token = '';

        $in_token = FALSE;
        $in_quote = FALSE;
        $quote_type = 0;

        for($i = 0; $i < strlen($exp); $i++)
        {
            $c = $exp[$i];
            if($c == '"' || $c == '\'')
            {
                $quote_type = $c;
                
                if(!$in_quote)
                {
                    $in_quote = TRUE;
                } elseif($c == $quote_type) {   // only end if it's the same type of quote
                    $in_quote = FALSE;
                    $tokens[] = new PL_ExpToken($token, EXP_VAL);
                    $token = '';
                    continue;
                }
            } else {
                if($in_quote)
                {
                    $token .= $c;
                }
            }

            if($in_quote) continue;


            /*if($c == ' ')
            {
                $in_token = FALSE;
                if($token != '') $tokens[] = new PL_ExpToken($token);
                $token = '';
                continue;
            } else */ if(isset(PL_Template::$precedence[$c])) {
                $in_token = FALSE;
                if($token != '') {
                    if($c == '(')
                    {
                        $tok = new PL_ExpToken($token, EXP_FUNC);
                    } else {
                        $tok = new PL_ExpToken($token);
                    }
                    $tokens[] = $tok;
                }
                $token = $c;
                switch($c)
                {
                    case '=': case '<': case '>': case '!':
                        $nc = substr($exp, $i+1, 1);
                        if($nc == '=')
                        {
                            $token .= $nc;
                            $i ++;
                        }
                        break;
                    case '&':
                    case '|':
                        $nc = substr($exp, $i+1, 1);
                        if($nc == $c)
                        {
                            $token .= $nc;
                            $i ++;
                        }
                        break;

                }
                if(count($tokens) > 0 && $token == '=' && $tokens[count($tokens)-1]->type == EXP_VAR)
                {
                    $tokens[count($tokens)-1]->assignment = TRUE;
                }
                if(count($tokens) > 1 && $tokens[count($tokens)-2]->op == '.' && $tokens[count($tokens)-1]->type == EXP_VAR)
                {
                    $tokens[count($tokens)-1]->assignment = TRUE;
                }
                /*
                if(count($tokens) > 2 && $tokens[count($tokens)-3]->type == EXP_VAR && $tokens[count($tokens)-2]->op == '==' && $tokens[count($tokens)-1]->type == EXP_VAR)
                {
                    $tokens[count($tokens)-3]->assignment = TRUE;
                    $tokens[count($tokens)-1]->assignment = TRUE;
                }
                */
                $tokens[] = new PL_ExpToken($token);
                $token = '';
            } else {
                $in_token = TRUE;
                $token .= $c;
            }

            if(!$in_token && $token != '')
            {
                if($token != '') $tokens[] = new PL_ExpToken($token);
                $token = '';
            }
        }

        if($in_token) {
            if($token != '') $tokens[] = new PL_ExpToken($token);
            $token = '';
        }

        return $tokens;
    }

    function parse_exp($tokens, $do_assignments = FALSE)
    {
        // expression parser based on http://en.wikipedia.org/wiki/Shunting_yard_algorithm
        // this function takes an infix expression and returns a postfix parsed array

        $ops = array();
        $result = array();

        foreach($tokens as $token)
        {
            if(!($token instanceof PL_ExpToken))
            {
                echo "Invalid token:";
                var_dump($token);exit;
            }
            if($do_assignments && $token->type == EXP_OP && $token->op == ' ')
            {
                continue;
            }
            
            if($token->op == ' ')
            {
            }
            elseif($token->type == EXP_OP && $token->op != '(' && $token->op != ')' && $token->op != ',')
            {
                // If the token is an operator, o1, then:
                // while there is an operator token, o2, at the top of the stack, and
                while(count($ops) > 0 && $ops[count($ops)-1]->type == EXP_OP &&
                    // either o1 is left-associative and its precedence is less than or equal to that of o2,
                    ((PL_Template::$left_assoc[$token->op]
                        && PL_Template::$precedence[$token->op] <= PL_Template::$precedence[$ops[count($ops)-1]->op])

                    // or o1 is right-associative and its precedence is less than that of o2,
                    || (!PL_Template::$left_assoc[$token->op]
                        &&  PL_Template::$precedence[$token->op] < PL_Template::$precedence[$ops[count($ops)-1]->op]) ))
                {
                    // pop o2 off the stack, onto the output queue;
                    $result[] = array_pop($ops);
                }

                // push o1 onto the stack.
                array_push($ops, $token);
            }
            elseif($token->op == '(')
            {
                // If the token is a left parenthesis, then push it onto the stack.
                array_push($ops, $token);
            }
            elseif($token->op == ')')
            {
                // If the token is a right parenthesis:
                // Until the token at the top of the stack is a left parenthesis, pop operators off the stack onto the output queue.
                while(count($ops) > 0 && $ops[count($ops)-1]->op != '(')
                {
                    $result[] = array_pop($ops);

                    // Pop the left parenthesis from the stack, but not onto the output queue.
                    // If the stack runs out without finding a left parenthesis, then there are mismatched parentheses.
                    if(count($ops) == 0)
                    {
                        show_error('Expression syntax error: mismatched parentheses');
                    }
                }

                if($ops[count($ops)-1]->op == '(')
                {
                    array_pop($ops);
                }

                if(count($ops) > 0 && $ops[count($ops)-1]->type == EXP_FUNC)
                {
                    $result[] = array_pop($ops);
                }
            }
            elseif($token->op == ',')
            {
                // If the token is a function argument separator (e.g., a comma):
                // Until the token at the top of the stack is a left parenthesis, pop operators off the stack onto the output queue.

                while(count($ops) > 0 && $ops[count($ops)-1]->op != '(') {
                    $result[] = array_pop($ops);

                    // If no left parentheses are encountered, either the separator was misplaced or parentheses were mismatched.
                    if(count($ops) == 0)
                    {
                        show_error('Expression syntax error: mismatched parentheses or comma');
                    }
                }
            }
            elseif($token->type == EXP_FUNC)
            {
                array_push($ops, $token);
            }
            else
            {
                // If the token is a value, then add it to the output queue.
                $result[] = $token;
            }
        }

        // When there are no more tokens to read:
        // While there are still operator tokens in the stack:
        while(count($ops) > 0)
        {
            // If the operator token on the top of the stack is a parenthesis, then there are mismatched parentheses.
            if($ops[count($ops)-1]->op == '(' || $ops[count($ops)-1]->op == ')')
            {
                show_error('Expression syntax error: mismatched parentheses');
            }

            // Pop the operator onto the output queue.
            $result[] = array_pop($ops);
        }

        return $result; // RPN version of expresion to pass to eval_exp()
    }

    function eval_exp($rpn, &$data, $do_assignments = FALSE)
    {
//          echo "<b>eval_exp</b> ".count($rpn)." cells<br/>";
//          $this->CI->load->helper('krumo');
//          krumo($rpn);
        
        $stack = array();
        for($i = 0; $i < count($rpn); $i ++)
        {
            $tok = $rpn[$i];
            switch($tok->type)
            {
                case EXP_VAL:
                    //echo "push VALUE " . $tok->val . "<br/>\n";
                    array_push($stack, $tok->val);
                    break;
                case EXP_VAR:
                    if($tok->assignment)
                    {
                        //echo "push assignment var " . $tok->var . "<br/>\n";
                        array_push($stack, $tok);
                    } else {
                        $val = '';
                        //echo $tok->var;
                        //var_dump($data);
                        if($this->lookup_var($tok->var, $data, $val))
                        {
                            //echo "push value of var " . $tok->var . " = ". (is_array($val) ? 'array' : $val) . "<br/>\n";
                            array_push($stack, $val);
                        } else {
                            //echo "push FALSE as value of unset var " . $tok->var . "<br/>\n";
                            array_push($stack, FALSE);
                        }
                    }
                    break;
                case EXP_OP:
                    switch($tok->op)
                    {
                        case '!':
                            $x = $this->prolib->stack_pop($stack);
                            $x = !$x;
                            array_push($stack, $x);
                            break;
                        case '*':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x * $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '/':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x / $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '%':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x % $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '>':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x > $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '<':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x < $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '+':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                $x = $x . $y;
                                array_push($stack, $x);
                            } else {
                                $x = $x + $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '-':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x - $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '==':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            //echo 'handle "'.$x.'" == "'.$y.'"<br/>';
                            $x = $x == $y;
                            //echo 'result ='.$x.'<br/>';
                             array_push($stack, $x);
                            break;
                        case '>=':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x >= $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '<=':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x <= $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '!=':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            $x = $x != $y;
                            array_push($stack, $x);
                            break;
                        case '&':
                        case '&&':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            $x = $x && $y;
                            array_push($stack, $x);
                            break;
                        case '|':
                        case '||':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            $x = $x || $y;
                            array_push($stack, $x);
                            break;
                        case '.':
                            $y = $this->prolib->stack_pop($stack);
                            $x = $this->prolib->stack_pop($stack);
                            if(is_array($x))
                            {
                                $x = $x[$y->var];
                            } else {
                                $x = $x->{$y->var};
                            }
                            array_push($stack, $x);
                            break;
                        case '=':
                            if($do_assignments)
                            {

                                $y = $this->prolib->stack_pop($stack);
                                $x = $this->prolib->stack_pop($stack);


                                if(!is_object($x))
                                {
                                    // echo "Unexpected non-object<br/>\n";
                                    // echo "Parsed expression:<br/>\n";
                                    //print_r($rpn);
                                    // echo "Current stack:<br/>\n";
                                    // print_r($stack);
                                    // echo "Items just pulled from stack (x, y):<br/>\n";
                                    // print_r($x);
                                    // print_r($y);
                                    show_error('Expression syntax error: unexpected operand, expecting variable name: '
                                        .print_r($x, TRUE)."    <br/>\n"
                                        .print_r($y, TRUE));
                                }
                                //echo "x = " . $x->var."<br/>";
                                //echo "y = " . $y."<br/>";

                                $data[$x->var] = $y;
                            }
                            break;
                    }
                case EXP_FUNC:
                    foreach($this->taglibs as $taglib)
                    {
                        if(method_exists($taglib, 'func_'.$tok->func))
                        {
                            $x = $taglib->{'func_'.$tok->func}($stack);
                            array_push($stack, $x);
                            break;
                        }
                    }
                    break;
            }
        }
//         echo '--- done ---<br/>';
//         krumo($stack);
        return $this->prolib->stack_pop($stack);
    }

    function to_array($data)
    {
        if($data instanceof RedBean_OODBBean)
        {
            // slow way because RedBean_OODBBean likes to use magic that doesn't work with an array cast
            $arr = array();
            foreach($data as $key => $val)
            {
                $arr[$key] = $val;
            }
            $data = $arr;
            unset($arr);
        } elseif(is_object($data)) {
            $data = (array)$data;
        }
        return $data;
    }

}

class PL_TemplateNode {
    var $type = 0;
    var $text = '';
    var $blocks = array(0 => array());
    var $block_names = array(0 => '');
    var $params = array();      // parsed params expression
    var $tmp_params = array();  // temporary params values for an individual call

    function __construct($type, $text='')
    {
        $this->type = $type;
        $this->text = $text;
    }

    function param($name, $default = FALSE, $type = '')
    {
        if(isset($this->tmp_params[$name]))
        {
            $result = $this->tmp_params[$name];
        } else {
            $result = $default;
        }
        
        if($type == 'bool')
        {
            $lower_result = strtolower($result);
            if($result 
                && $lower_result != 'no'    && $lower_result != 'n'
                && $lower_result != 'false' && $lower_result != 'f'
                && $lower_result != 'off')
            {
                $result = TRUE;
            } else {
                $result = FALSE;
            }
        }
        
        return $result;
    }
}

class PL_ExpToken {
    var $type = 0;
    var $var = '';
    var $assignment = FALSE;
    var $val = '';
    var $op = '';
    var $func = '';


    function __construct($token, $type = 0)
    {
        if($type == 0)
        {
            if(isset(PL_Template::$precedence[$token]))
            {
                $type = EXP_OP;
            } elseif(is_numeric($token)) {
                $type = EXP_VAL;
            } elseif($token[0] == '"' || $token[0] == '\'') {
                if($token[strlen($token)-1] == $token[0])
                {
                    // remove the quotes
                    $token = substr($token, 1, strlen($token)-2);
                }
                $type = EXP_VAL;

            } else {
                $type = EXP_VAR;
            }
        }

        $this->type = $type;

        switch($type)
        {
            case EXP_OP:
                $this->op = $token;
                break;
            case EXP_VAL:
                $this->val = $token;
                break;
            case EXP_VAR:
                $this->var = trim($token);
                break;
            case EXP_FUNC:
                $this->func = trim($token);
                break;
        }
    }
}

class PL_TemplateBuiltins {
    function __construct()
    {
        global $PROLIB;
        $this->prolib = $PROLIB;
        $this->CI = &get_instance();
    }

    function _if($node, $data)
    {
        //echo '<div style="color: black; background:white;"><h3>IF!</h3><br/>';
        if(!isset($node->rpn))
        {
            $node->rpn = $this->prolib->pl_template->parse_exp($node->params);
        }
        
        $result = $this->prolib->pl_template->eval_exp($node->rpn, $data);

        $has_else = FALSE;
        if(isset($node->block_names[1])) {
            if($node->block_names[1] == 'else') {
                $has_else = TRUE;
            } else {
                show_error('Syntax error: Conditional expects {if:else} block, not {if:'.$node->block_names[1].'}');
            }
        }
        if($result)
        {
            //echo 'IF=true<br>';
            // apply the true template code (block 0)
            return $this->prolib->pl_template->apply($node, array($data), 0);
        } elseif($has_else) {
            //echo 'IF=false<br>';
            // apply the else template code (block 1)
            return $this->prolib->pl_template->apply($node, array($data), 1);
        }
    }

    function _base_url($node, $data)
    {
        return '/';
    }
}
