<?php

/**
 * UserFunction.php - Jaxon user function plugin
 *
 * This class registers user defined functions, generates client side javascript code,
 * and calls them on user request
 *
 * @package jaxon-core
 * @author Jared White
 * @author J. Max Wilson
 * @author Joseph Woolley
 * @author Steffen Konerow
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright Copyright (c) 2005-2007 by Jared White & J. Max Wilson
 * @copyright Copyright (c) 2008-2010 by Joseph Woolley, Steffen Konerow, Jared White  & J. Max Wilson
 * @copyright 2016 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Request\Plugin;

use Jaxon\Jaxon;
use Jaxon\Plugin\Request as RequestPlugin;
use Jaxon\Request\Manager as RequestManager;

class UserFunction extends RequestPlugin
{
    use \Jaxon\Utils\Traits\Container;

    /**
     * The registered user functions
     *
     * @var array
     */
    protected $aFunctions;

    /**
     * The name of the function that is being requested (during the request processing phase)
     *
     * @var string
     */
    protected $sRequestedFunction;

    public function __construct()
    {
        $this->aFunctions = array();

        $this->sRequestedFunction = null;
        
        if(isset($_GET['jxnfun']))
        {
            $this->sRequestedFunction = $_GET['jxnfun'];
        }
        if(isset($_POST['jxnfun']))
        {
            $this->sRequestedFunction = $_POST['jxnfun'];
        }
    }

    /**
     * Return the name of this plugin
     *
     * @return string
     */
    public function getName()
    {
        return 'UserFunction';
    }

    /**
     * Register a user defined function
     *
     * @param array         $aArgs                An array containing the function specification
     *
     * @return \Jaxon\Request\Request
     */
    public function register($aArgs)
    {
        if(count($aArgs) > 1)
        {
            $sType = $aArgs[0];

            if($sType == Jaxon::USER_FUNCTION)
            {
                $xUserFunction = $aArgs[1];

                if(!($xUserFunction instanceof \Jaxon\Request\Support\UserFunction))
                    $xUserFunction = new \Jaxon\Request\Support\UserFunction($xUserFunction);

                if(count($aArgs) > 2)
                {
                    if(is_array($aArgs[2]))
                    {
                        foreach($aArgs[2] as $sName => $sValue)
                        {
                            $xUserFunction->configure($sName, $sValue);
                        }
                    }
                    else
                    {
                        $xUserFunction->configure('include', $aArgs[2]);
                    }
                }
                $this->aFunctions[$xUserFunction->getName()] = $xUserFunction;

                return $xUserFunction->generateRequest();
            }
        }

        return false;
    }

    /**
     * Generate a hash for the registered user functions
     *
     * @return string
     */
    public function generateHash()
    {
        $sHash = '';
        foreach($this->aFunctions as $xFunction)
        {
            $sHash .= $xFunction->getName();
        }
        return md5($sHash);
    }

    /**
     * Generate client side javascript code for the registered user functions
     *
     * @return string
     */
    public function getScript()
    {
        $code = '';
        foreach($this->aFunctions as $xFunction)
        {
            $code .= $xFunction->getScript();
        }
        return $code;
    }

    /**
     * Check if this plugin can process the incoming Jaxon request
     *
     * @return boolean
     */
    public function canProcessRequest()
    {
        // Check the validity of the function name
        if(($this->sRequestedFunction) && !$this->validateFunction($this->sRequestedFunction))
        {
            $this->sRequestedFunction = null;
        }
        return ($this->sRequestedFunction != null);
    }

    /**
     * Process the incoming Jaxon request
     *
     * @return boolean
     */
    public function processRequest()
    {
        if(!$this->canProcessRequest())
            return false;

        $aArgs = $this->getRequestManager()->process();

        if(array_key_exists($this->sRequestedFunction, $this->aFunctions))
        {
            $this->aFunctions[$this->sRequestedFunction]->call($aArgs);
            return true;
        }
        // Unable to find the requested function
        throw new \Jaxon\Exception\Error('errors.functions.invalid', array('name' => $this->sRequestedFunction));
    }
}
