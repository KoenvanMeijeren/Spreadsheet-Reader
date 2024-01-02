<?php

namespace KoenVanMeijeren\phpcs\Variables;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

final class VariablesMustUseSnakeCase implements Sniff
{
    public function register(): array
    {
        return [T_VARIABLE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $varName = $tokens[$stackPtr]['content'];

        // Check if variable name is in snake_case format
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $varName)) {
            $error = 'Variable name "%s" is not in snake_case format';
            $data = array($varName);
            $phpcsFile->addError($error, $stackPtr, 'NotSnakeCase', $data);
        }
    }
}
