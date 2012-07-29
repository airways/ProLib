<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @package ProLib
 * @author Isaac Raway <isaac.raway@gmail.com>
 *
 * Copyright (c)2009, 2010. Isaac Raway and MetaSushi, LLC. All rights reserved.
 *
 * This source is commercial software. Use of this software requires a site license for each
 * domain it is used on. Use of this software or any of it's source code without express
 * written permission in the form of a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 **/

// Basic
define('PLS_MSG', 1);
define('PLS_IF', 2);
define('PLS_CALL', 3);
define('PLS_FOR', 4);

// ProForm
define('PLS_CANCEL_SUBMIT', 100);

// Expressions
define('EXP_VAL', 1);
define('EXP_OP', 2);
define('EXP_VAR', 3);
define('EXP_FUNC', 4);

if(!class_exists('PL_Script')) {
class PL_Script {
    var $register = array();
    var $funclibs = array();

    function init()
    {
        global $PROLIB;
        $this->prolib = &$PROLIB;

        foreach($this->prolib->pl_drivers->get_drivers('funclib') as $driver)
        {
            $this->register_funclib($driver);
        }
    }

    function register_funclib($funclib)
    {
        $this->funclibs[] = $funclib;
    }

    function execute($script)
    {
        if(!is_array($script))
        {
            show_error('Invalid script object passed to PL_Script::execute()');
            exit;
        }

        // The register is used to pass back multiple pieces of data, using different
        // instructions
        $this->register = array(
            'messages' => array(),
            'cancel_submit' => false,
        );
        
        $this->run($script);

        return $this->register;
    }

    function run($script)
    {
        // Each "line" of a script is itself an array which starts
        // with a constant that indicates the type of instruction
        for($i = 0; $i < sizeof($script); $i++)
        {
//             echo $i;
            $line = $script[$i];
            if(!is_array($line))
            {
                echo 'Invalid script. Line is not an array:';
                print_r($line);
                exit;
            }
//             echo '<pre>';print_r($line);
            
            switch($line[0])
            {
                case PLS_IF:
                    if(sizeof($line) >= 3)
                    {
                        $result = $this->exec_exp($line[1]);
                        if($result && sizeof($line) >= 3)
                        {
                            $this->run($line[2]);
                        } elseif(sizeof($line) >= 4) {
                            $this->run($line[3]);
                        }
                    } else exit('Incomplete parameters passed to IF');
                    break;
                case PLS_FOR:
                    //Params: var, start, increment, limit
                    if(sizeof($line >= 5))
                    {
                        // Run each argument expression once to get it's value:
                        
                        $start = $this->exec_exp($line[2]);
                        $increment = $this->exec_exp($line[3]);
                        $limit = $this->exec_exp($line[4]);

                        for($f = $line[1]; $f < $limit; $f += $line[2])
                        {
                            $this->run($line[4]);
                        }
                    } else exit('Incomplete parameters passed to FOR');
                    break;
                case PLS_CALL:
                    // Allows for driver-defined callable functions to be made
                    // available by name in every add-on that uses PL_Script, without
                    // requiring adjustment of their UI to inject it.
                    // Params: statement name[, parameters]
                    if(sizeof($line >= 2))
                    {
                        foreach($this->funclibs as $funclib)
                        {
                            if(method_exists($funclib, 'call_'.$line[1]))
                            {
                                $funclib->{'call_'.$line[1]}($this, $line);
                                break;
                            }
                        }
                    } else exit('Incomplete parameters passed to CALL');
                    break;
                case PLS_MSG:
                    // Params: message
                    if(sizeof($line >= 2))
                    {
                        $this->register['messages'][] = $line[1];
                    } else exit('Incomplete parameters passed to MSG');
                    break;
                case PLS_CANCEL_SUBMIT:
                    $this->register['cancel_submit'] = true;
                    break;
                default:
                    // Anything callable here would require use of a custom
                    // script UI to inject into the script.
                    foreach($this->funclibs as $funclib)
                    {
                        if(method_exists($funclib, 'do_inst'))
                        {
                            if($funclib->do_inst($line)) break;
                        }
                    }
            }
        }

    }










    // Expressions //
    function exec_exp($exp)
    {
        $result = $this->tokenize($exp);
        $result = $this->parse_exp($result);
        $data = array();
        $result = $this->eval_exp($result, $data);
        return $result;
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

        // walk over the variable segments, digging into each
        // layer of the data structure if we find it
        /*if(count($var_segments) > 1)
        {
            var_dump($var_segments);
            var_dump($data_node);
        }*/
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
        '|' => 0,
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
        '|' => TRUE,    // "
        '=' => FALSE,   // right to left
        '==' => FALSE,  // "
        '!=' => FALSE,  // "
        '>=' => FALSE,  // "
        '<=' => FALSE,  // "
        '!' => FALSE,   // "
        ' ' => FALSE,   // "
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
            } else */ if(isset(PL_Script::$precedence[$c])) {
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
                }
                if(count($tokens) > 0 && $token == '=' && $tokens[count($tokens)-1]->type == EXP_VAR)
                {
                    $tokens[count($tokens)-1]->assignment = TRUE;
                }
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
            if($token->type == EXP_OP && $token->op != '(' && $token->op != ')' && $token->op != ',')
            {
                // If the token is an operator, o1, then:
                // while there is an operator token, o2, at the top of the stack, and
                while(count($ops) > 0 && $ops[count($ops)-1]->type == EXP_OP &&
                    // either o1 is left-associative and its precedence is less than or equal to that of o2,
                    ((PL_Script::$left_assoc[$token->op]
                        && PL_Script::$precedence[$token->op] <= PL_Script::$precedence[$ops[count($ops)-1]->op])

                    // or o1 is right-associative and its precedence is less than that of o2,
                    || (!PL_Script::$left_assoc[$token->op]
                        &&  PL_Script::$precedence[$token->op] < PL_Script::$precedence[$ops[count($ops)-1]->op]) ))
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
                    // echo "push " . $tok->val . "<br/>\n";
                    array_push($stack, $tok->val);
                    break;
                case EXP_VAR:
                    if($tok->assignment)
                    {
                        // echo "push assignment var " . $tok->var . "<br/>\n";
                        array_push($stack, $tok);
                    } else {
                        $val = '';
                        //echo $tok->var;
                        //var_dump($data);
                        if($this->lookup_var($tok->var, $data, $val))
                        {
                            // echo "push value of var " . $tok->var . " = ". $val . "<br/>\n";
                            array_push($stack, $val);
                        } else {
                            // echo "push FALSE as value of unset var " . $tok->var . "<br/>\n";
                            array_push($stack, FALSE);
                        }
                    }
                    break;
                case EXP_OP:
                    switch($tok->op)
                    {
                        case '!':
                            $x = stack_pop($stack);
                            $x = !$x;
                            array_push($stack, $x);
                            break;
                        case '*':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x * $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '/':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x / $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '%':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x % $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '>':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x > $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '<':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x < $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '+':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x + $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '-':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x - $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '==':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
//                             echo 'handle "'.$x.'" == "'.$y.'"<br/>';
                            $x = $x == $y;
//                             echo 'result ='.$x.'<br/>';
//                             array_push($stack, $x);
                            break;
                        case '>=':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x >= $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '<=':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            if(!is_numeric($x) || !is_numeric($y))
                            {
                                array_push($stack, 0);
                            } else {
                                $x = $x <= $y;
                                array_push($stack, $x);
                            }
                            break;
                        case '!=':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            $x = $x != $y;
                            array_push($stack, $x);
                            break;
                        case '&':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            $x = $x && $y;
                            array_push($stack, $x);
                            break;
                        case '|':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            $x = $x || $y;
                            array_push($stack, $x);
                            break;
                        case '.':
                            $y = stack_pop($stack);
                            $x = stack_pop($stack);
                            $x = $x . $y;
                            array_push($stack, $x);
                            break;
                        case '=':
                            if($do_assignments)
                            {

                                $y = stack_pop($stack);
                                $x = stack_pop($stack);


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
                    foreach($this->funclibs as $funclib)
                    {
                        if(method_exists($funclib, 'func_'.$tok->func))
                        {
                            $x = $funclib->{'func_'.$tok->func}($stack);
                            array_push($stack, $x);
                            break;
                        }
                    }
                    break;
            }
        }
//         echo '--- done ---<br/>';
//         krumo($stack);
        return stack_pop($stack);
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
}} // class PL_Script

if(!class_exists('PL_ExpToken')) {
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
            if(isset(PL_Script::$precedence[$token]))
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
}}


if(!function_exists('stack_pop')) {

function stack_pop(&$stack)
{
    $loop = TRUE;
    while($loop)
    {
        $loop = FALSE;
        $result = array_pop($stack);
        if(isset($token->type) && isset($token->op))
        {
            if($token->type == EXP_OP && $token->op == ' ')
            {
                $loop = TRUE;
            }
        }
    }
    return $result;
}}
