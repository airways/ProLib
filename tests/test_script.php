<?php

class Test_Script
{
    function run()
    {
        prolib($this);

        ob_start();
        
        echo 'Running script...<hr/>';
        
        $register = $this->prolib->pl_script->execute(array(
            array(PLS_MSG, 'Basic reality check...'),
            array(PLS_IF, '1 == 1',
                array(
                    array(PLS_MSG, 'Result is TRUE!'),
                    array(PLS_CANCEL_SUBMIT),
                ),
                array(array(PLS_MSG, 'Result is FALSE!'))
            ),
            array(PLS_MSG, 'What a test!'),
        ));

        echo '<hr/><b>Result Register:</b><br/>';
        echo '<pre>';
        print_r($register);
        echo '</pre>';
        
        
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
}

