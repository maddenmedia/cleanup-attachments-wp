# Clean Up Duplicate WordPress Media Files
Delete Duplicated WordPress Media Files and Auto Replace Them In the Content, Be aware this is a slow process and should only be used on a need case basis where human media clean up could take awhile.

Place these files in a dir named `cleanup-attachments` inside your `/web` dir and run from the php cli. Will take awhile to process.

Run the `cleanup-attachments-by-unused.php` from the browser, This will remove any unused files that are not in the database. Please run `wp media regenerate` in the CLI after tool is done.

Commands: `php web/cleanup-attachments/cleanup-attachments.php` or `php web/cleanup-attachments/cleanup-attachments-by-hash.php` 


## DO NOT USE THE HASH METHOD YET AS IT IS BEING DEVELOPED STILL
