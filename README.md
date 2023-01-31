# Clean Up Duplicate WordPress Media Files
Delete Duplicated WordPress Media Files and Auto Replace Them In the Content, Be aware this is a slow process and should only be used on a need case basis where human media clean up could take awhile.

Place these files in a dir named `cleanup-attachments` inside your `/web` dir and run from the php cli. Will take awhile to process.

Command: `php web/cleanup-attachments/cleanup-attachments.php`

Accessing the URL direct runs the by filename and to access the hash add `?hashCompareCleanUp=y` to your URL.

## By Hash
![cleanup-attachments-hash](https://user-images.githubusercontent.com/109692527/208826547-49d7b906-dbb4-481c-a158-b19aa48e9b5e.JPG)
