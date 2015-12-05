PHP_SSH2MST 		- SSH2 Multi Stream Transfer class (main)
PHP_SSH2MSTSBase	- Base class for transfer streams...
PHP_SSH2MSTSUp		- Upload Stream class for single transfer...
PHP_SSH2MSTSDown	- Download Stream class for single transfer...
--------------------------

Main purpose of this class is to allow transfer of single file over miltiple SSH2 streams to gain maximal transfer speed...
coz using original functions of PHP ...gives shitty speeds:

- copy()			- SLOW ...~350Kb/sec
- stream_copy_to_stream()	- FAST ...~650Kb/sec (stream is set to blocking)
- ssh2_scp_send()		- FAST ...~650Kb/sec
....and most reffered to class by "stackoverlow.com" site: - please stop doing that ... you are not helping...when people asking how to fix PHP short commings !!!!!!!!!!!!)
- Net_SFTP()->put()		- SLOW ...~320Kb/sec (phpseclib1.0.0/SFTP.php)

- This Script:
	10 streams (sequential transfer (not used anymore))	- FAST ...~1.2Mb/sec
	10 streams (re-ordedred transfer)			- FAST ...~1.5Mb/sec
	Note on speed: ...
		all speeds were measured between dedicated server on Leaseweb in Germany (Frankfurt) and Edis VPS (Switzerland) (2 core ...max load at any time 5% CPU...so screw anyone who would say that SSH is slow coz of extensive CPU usage ......they just slowing it down coz they need the time for DPI)
		During the day - speed remained at 1.2 download and 2.5 upload ...(heavily clamped down...)
		After office hours (17h-18h) ...speed increased up to 2MB down and 3.5Mb up ...
		Any request to clarify situation at Leaseweb - gave no results..

		DO NOT HOST YOUR SERVERS IN GERMANY ...!!!!!!

Basically what this class does - it uses none blocking SSH2 streams for transfer
with some little hacks to allow the completion check to complement missing PHP functionality.
Class is capable sending single file over multiple streams ....and re-use established connections for consequent multi-file transfers


Here are some measured values for uploading a single file (44Mb .zip):
------------------------------------------------------------------------
System setup:
	- Source:
				Host:		Leaseweb (DE) Dedicated server located in Frankfurt
				OS:		Windows Server 2008 Server R2
				Memory:		16GB
				CPU:		Intel Xeon E3 1220 @ 3.10Mhz
				Network:	1Gbit (200mbit guaranteed badnwidth)
	- Destination:
				Host:		Edis (CH) OVZ server located in Zurich
				OS:		Linux Debian 7 (wheezy)
				Memory:		2Gb
				CPU:		Intel Xeon E5-2630 v2 @ 2.60GHz (2 cores available)
				Network:	1Gbit (unlimited)
	- Script config:
				Streams:	10
				Buffer: 	8KB (see notes for "PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_UPLOAD" constant)
------------------------------------------------------------------------
All Tests performed after office hours (after 20:00 European time ...when total load on network subsided)
------------------------------------------------------------------------
5ms		=> 960KB/sec
4ms		=> 1.1MB/sec (speed graph - straight line)
3ms		=> 1.2MB/sec (speed graph - straight line)
2ms		=> 1.5MB/sec (speed graph - straight line...but script is almost uncontrollable :)...CTRL/C doestnt work as easy as expected )
1ms		=> 1.8MB/sec (speed graph - straight line...beware of warewolf ..i mean not being able to stop the script)
0.5ms		> 2.0MB/sec (speed graph - jaggy eddged line......stopping the script is not possible now over RemoteDesktop connection)
0.1ms		=> 2.0MB/sec Max (Speed graph - deep drops down to 1.8 average keeps at 2.0MB/sec....)
No delay - NOT RECOMMENDED !!! - 2.1MB/sec Max (Speed graph - deep drops down to 1.6Mb/sec.... average at 1.9MB/sec)
------------------------------------------------------------------------
CPU usage on both computers never exceeded 5% during any of the transfers.....
------------------------------------------------------------------------
Minor note - all measurements were made with DEBUG flag set to ON...producing thousands of debug messages.
without any debug info being printed - script performance about 150% higher...(what was 2.0MB/sec...will go up to 3.0MB/sec...roughly calculated)
------------------------------------------------------------------------


how to use:



require('php_ssh2mst.php');
$remote_file='/home/UPLOADED_TEST_3.gro';
$local_file='c:/UPLOAD_TEST_3.gro';
$PHP_SSH2MST->uploadFile($remote_file,$local_file);

echo 'SFTP Copy Time (Global): ' . $PHP_SSH2MST->last_transfer_time . ' sec (' . $PHP_SSH2MST->bytes2string($PHP_SSH2MST->last_file_size) . ' - ' . $PHP_SSH2MST->bytes2string($PHP_SSH2MST->last_transfer_speed) . "/sec...combined average speed= " . $PHP_SSH2MST->bytes2string($PHP_SSH2MST->last_combined_transfer_speed) . "/sec)\n\n";


//Minor note ...when debug flag is set to 0 .... 
//Upload speed is ~6mb/sec with 10 streams  :)...enjoy
