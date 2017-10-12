<?php
/**
 * Squiz_Sniffs_NamingConventions_ValidVariableNameSniff.
 *
 * PHP version 5
 *
 * @category  PHP
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 *
 * @see      http://pear.php.net/package/PHP_CodeSniffer
 */
if (false === class_exists('PHP_CodeSniffer_Standards_AbstractVariableSniff', true)) {
    throw new PHP_CodeSniffer_Exception('Class PHP_CodeSniffer_Standards_AbstractVariableSniff not found');
}

/**
 * Squiz_Sniffs_NamingConventions_ValidVariableNameSniff.
 *
 * Checks the naming of variables and member variables.
 *
 * @category  PHP
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 *
 * @version   Release: 1.5.2
 *
 * @see      http://pear.php.net/package/PHP_CodeSniffer
 */
class ZendModStandard_Sniffs_NamingConventions_ValidVariableNameSniff extends PHP_CodeSniffer_Standards_AbstractVariableSniff
{
    /**
     * Tokens to ignore so that we can find a DOUBLE_COLON.
     *
     * @var array
     */
    private $_ignore = [
                        T_WHITESPACE,
                        T_COMMENT,
                       ];

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile the file being scanned
     * @param int                  $stackPtr  the position of the current token in the
     *                                        stack passed in $tokens
     */
    protected function processVariable(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $varName = ltrim($tokens[$stackPtr]['content'], '$');

        $phpReservedVars = [
                            '_SERVER',
                            '_GET',
                            '_POST',
                            '_REQUEST',
                            '_SESSION',
                            '_ENV',
                            '_COOKIE',
                            '_FILES',
                            'GLOBALS',
                           ];

        // If it's a php reserved var, then its ok.
        if (true === in_array($varName, $phpReservedVars)) {
            return;
        }

        $objOperator = $phpcsFile->findNext([T_WHITESPACE], ($stackPtr + 1), null, true);
        if ($tokens[$objOperator]['code'] === T_OBJECT_OPERATOR) {
            // Check to see if we are using a variable from an object.
            $var = $phpcsFile->findNext([T_WHITESPACE], ($objOperator + 1), null, true);
            if ($tokens[$var]['code'] === T_STRING) {
                // Either a var name or a function call, so check for bracket.
                $bracket = $phpcsFile->findNext([T_WHITESPACE], ($var + 1), null, true);

                if ($tokens[$bracket]['code'] !== T_OPEN_PARENTHESIS) {
                    $objVarName = $tokens[$var]['content'];

                    // There is no way for us to know if the var is public or private,
                    // so we have to ignore a leading underscore if there is one and just
                    // check the main part of the variable name.
                    $originalVarName = $objVarName;
                    if ('_' === substr($objVarName, 0, 1)) {
                        $objVarName = substr($objVarName, 1);
                    }

                    if (false === PHP_CodeSniffer::isCamelCaps($objVarName, false, true, false)) {
                        $error = 'Variable "%s" is not in valid camel caps format';
                        $data = [$originalVarName];
                        $phpcsFile->addError($error, $var, 'NotCamelCaps', $data);
                    }
                    /*
                                else if (preg_match('|\d|', $objVarName)) {
                                            $warning = 'Variable "%s" contains numbers but this is discouraged';
                                            $data    = array($originalVarName);
                                            $phpcsFile->addWarning($warning, $stackPtr, 'ContainsNumbers', $data);
                                        }
                    */
                }//end if
            }//end if
        }//end if

        // There is no way for us to know if the var is public or private,
        // so we have to ignore a leading underscore if there is one and just
        // check the main part of the variable name.
        $originalVarName = $varName;
        if ('_' === substr($varName, 0, 1)) {
            $objOperator = $phpcsFile->findPrevious([T_WHITESPACE], ($stackPtr - 1), null, true);
            if ($tokens[$objOperator]['code'] === T_DOUBLE_COLON) {
                // The variable lives within a class, and is referenced like
                // this: MyClass::$_variable, so we don't know its scope.
                $inClass = true;
            } else {
                $inClass = $phpcsFile->hasCondition($stackPtr, [T_CLASS, T_INTERFACE, T_TRAIT]);
            }

            if (true === $inClass) {
                $varName = substr($varName, 1);
            }
        }

        if (false === PHP_CodeSniffer::isCamelCaps($varName, false, true, false)) {
            $error = 'Variable "%s" is not in valid camel caps format';
            $data = [$originalVarName];
            $phpcsFile->addError($error, $stackPtr, 'NotCamelCaps', $data);
        }
        /*
                else if (preg_match('|\d|', $varName)) {
                    $warning = 'Variable "%s" contains numbers but this is discouraged';
                    $data    = array($originalVarName);
                    $phpcsFile->addWarning($warning, $stackPtr, 'ContainsNumbers', $data);
                }
        */
    }

    //end processVariable()

    /**
     * Processes class member variables.
     *
     * @param PHP_CodeSniffer_File $phpcsFile the file being scanned
     * @param int                  $stackPtr  the position of the current token in the
     *                                        stack passed in $tokens
     */
    protected function processMemberVar(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $varName = ltrim($tokens[$stackPtr]['content'], '$');
        $memberProps = $phpcsFile->getMemberProperties($stackPtr);
        $public = ('public' === $memberProps['scope']);

        if (true === $public) {
            if ('_' === substr($varName, 0, 1)) {
                $error = 'Public member variable "%s" must not contain a leading underscore';
                $data = [$varName];
                $phpcsFile->addError($error, $stackPtr, 'PublicHasUnderscore', $data);

                return;
            }
        } else {
            if ('_' !== substr($varName, 0, 1)) {
                $scope = ucfirst($memberProps['scope']);
                $error = '%s member variable "%s" must contain a leading underscore';
                $data = [
                          $scope,
                          $varName,
                         ];
                $phpcsFile->addError($error, $stackPtr, 'PrivateNoUnderscore', $data);

                return;
            }
        }

        if (false === PHP_CodeSniffer::isCamelCaps($varName, false, $public, false)) {
            $error = 'Variable "%s" is not in valid camel caps format';
            $data = [$varName];
            $phpcsFile->addError($error, $stackPtr, 'MemberVarNotCamelCaps', $data);
        }
        /*
                else if (preg_match('|\d|', $varName)) {
                    $warning = 'Variable "%s" contains numbers but this is discouraged';
                    $data    = array($varName);
                    $phpcsFile->addWarning($warning, $stackPtr, 'MemberVarContainsNumbers', $data);
                }
        */
    }

    //end processMemberVar()

    /**
     * Processes the variable found within a double quoted string.
     *
     * @param PHP_CodeSniffer_File $phpcsFile the file being scanned
     * @param int                  $stackPtr  the position of the double quoted
     *                                        string
     */
    protected function processVariableInString(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $phpReservedVars = [
                            '_SERVER',
                            '_GET',
                            '_POST',
                            '_REQUEST',
                            '_SESSION',
                            '_ENV',
                            '_COOKIE',
                            '_FILES',
                            'GLOBALS',
                           ];

        if (0 !== preg_match_all('|[^\\\]\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|', $tokens[$stackPtr]['content'], $matches)) {
            foreach ($matches[1] as $varName) {
                // If it's a php reserved var, then its ok.
                if (true === in_array($varName, $phpReservedVars)) {
                    continue;
                }

                // There is no way for us to know if the var is public or private,
                // so we have to ignore a leading underscore if there is one and just
                // check the main part of the variable name.
                $originalVarName = $varName;
                if ('_' === substr($varName, 0, 1)) {
                    if (true === $phpcsFile->hasCondition($stackPtr, [T_CLASS, T_INTERFACE, T_TRAIT])) {
                        $varName = substr($varName, 1);
                    }
                }

                if (false === PHP_CodeSniffer::isCamelCaps($varName, false, true, false)) {
                    $varName = $matches[0];
                    $error = 'Variable "%s" is not in valid camel caps format';
                    $data = [$originalVarName];
                    $phpcsFile->addError($error, $stackPtr, 'StringVarNotCamelCaps', $data);
                } elseif (preg_match('|\d|', $varName)) {
                    $warning = 'Variable "%s" contains numbers but this is discouraged';
                    $data = [$originalVarName];
                    $phpcsFile->addWarning($warning, $stackPtr, 'StringVarContainsNumbers', $data);
                }
            }
        }//end if
    }

    //end processVariableInString()
}//end class
