@echo off
setlocal
SET PHP=D:\php\php.exe
%PHP% -f %1\hooks\post-commit-log.php %*