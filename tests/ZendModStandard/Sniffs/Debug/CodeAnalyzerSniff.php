<?php
/**
 * Zend_Sniffs_Debug_CodeAnalyzerSniff.
 *
 * PHP version 5
 *
 * @category  PHP
 *
 * @author    Holger Kral <holger.kral@zend.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 *
 * @see      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * Zend_Sniffs_Debug_CodeAnalyzerSniff.
 *
 * Runs the Zend Code Analyzer (from Zend Studio) on the file.
 *
 * @category  PHP
 *
 * @author    Holger Kral <holger.kral@zend.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 *
 * @version   Release: 1.5.2
 *
 * @see      http://pear.php.net/package/PHP_CodeSniffer
 */
class ZendModStandard_Sniffs_Debug_CodeAnalyzerSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array(int)
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }

    //end register()

    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param PHP_CodeSniffer_File $phpcsFile the file where the token was found
     * @param int                  $stackPtr  the position in the stack where
     *                                        the token was found
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        // Because we are analyzing the whole file in one step, execute this method
        // only on first occurrence of a T_OPEN_TAG.
        $prevOpenTag = $phpcsFile->findPrevious(T_OPEN_TAG, ($stackPtr - 1));
        if (false !== $prevOpenTag) {
            return;
        }

        $fileName = $phpcsFile->getFilename();

        $analyzerPath = PHP_CodeSniffer::getConfigData('zend_ca_path');
        if (true === is_null($analyzerPath)) {
            return;
        }

        // In the command, 2>&1 is important because the code analyzer sends its
        // findings to stderr. $output normally contains only stdout, so using 2>&1
        // will pipe even stderr to stdout.
        $cmd = $analyzerPath.' '.$fileName.' 2>&1';

        // There is the possibility to pass "--ide" as an option to the analyzer.
        // This would result in an output format which would be easier to parse.
        // The problem here is that no cleartext error messages are returnwd; only
        // error-code-labels. So for a start we go for cleartext output.
        $exitCode = exec($cmd, $output, $retval);

        // $exitCode is the last line of $output if no error occures, on error it
        // is numeric. Try to handle various error conditions and provide useful
        // error reporting.
        if (true === is_numeric($exitCode) && $exitCode > 0) {
            if (true === is_array($output)) {
                $msg = join('\n', $output);
            }

            throw new PHP_CodeSniffer_Exception("Failed invoking ZendCodeAnalyzer, exitcode was [$exitCode], retval was [$retval], output was [$msg]");
        }

        if (true === is_array($output)) {
            $tokens = $phpcsFile->getTokens();

            foreach ($output as $finding) {
                // The first two lines of analyzer output contain
                // something like this:
                // > Zend Code Analyzer 1.2.2
                // > Analyzing <filename>...
                // So skip these...
                $res = preg_match("/^.+\(line ([0-9]+)\):(.+)$/", $finding, $regs);
                if (true === empty($regs) || false === $res) {
                    continue;
                }

                // Find the token at the start of the line.
                $lineToken = null;
                foreach ($tokens as $ptr => $info) {
                    if ($info['line'] == $regs[1]) {
                        $lineToken = $ptr;
                        break;
                    }
                }

                if (null !== $lineToken) {
                    $phpcsFile->addWarning(trim($regs[2]), $ptr, 'ExternalTool');
                }
            }//end foreach
        }//end if
    }

    //end process()
}//end class
