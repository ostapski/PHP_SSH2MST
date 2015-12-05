<?
//#######################################################################################################################################################################
//a finger up the ars to one who said that script should be few lines long !!!
//#######################################################################################################################################################################
//##		[Author]				; Alexandre Ostanine (ostap333@hotmail.com)
//##		[Created]	  		; 23/11/2015
//##		[Version]				; 1.2.2
//##		[Description] 	; PHP_SSH2MST 			- SSH2 Multi Stream Transfer class (main)
//##										;---------------------------
//##										;	sub classes:
//##										; PHP_SSH2MSTSBase	- Base class for transfer streams...
//##										; PHP_SSH2MSTSUp		- Upload Stream class for single transfer...
//##										;	PHP_SSH2MSTSDown	- Download Stream class for single transfer...
//##										;---------------------------
//##										;
//##										; Main purpose of this class is to allow transfer of single file over miltiple SSH2 streams to gain maximal transfer speed...
//##										; coz using original functions of PHP ...gives shitty speeds:
//##										;
//##										; - copy()																- SLOW ...~350Kb/sec
//##										; - stream_copy_to_stream()								- FAST ...~650Kb/sec (stream is set to blocking)
//##										; - ssh2_scp_send()												- FAST ...~650Kb/sec
//##										;.....and most reffered to class by "stackoverlow.com" site: - please stop doing that ... you are not helping...when people asking how to fix PHP short commings !!!!!!!!!!!!)
//##										; - Net_SFTP()->put()											- SLOW ...~320Kb/sec (phpseclib1.0.0/SFTP.php)
//##										;
//##										;	- This Script:
//##										;		10 streams (sequential transfer (not used anymore))			- FAST ...~1.2Mb/sec
//##										;		10 streams (re-ordedred transfer)												- FAST ...~1.5Mb/sec
//##										;		Note on speed: ...
//##										;			all speeds were measured between dedicated server on Leaseweb in Germany (Frankfurt) and Edis VPS (Switzerland) (2 core ...max load at any time 5% CPU...so screw anyone who would say that SSH is slow coz of extensive CPU usage ......they just slowing it down coz they need the time for DPI)
//##										;			During the day - speed remained at 1.2 download and 2.5 upload ...(heavily clamped down...)
//##										;			After office hours (17h-18h) ...speed increased up to 2MB down and 3.5Mb up ...
//##										;			Any request to clarify situation at Leaseweb - gave no results..
//##										;
//##										;			DO NOT HOST YOUR SERVERS IN GERMANY ...!!!!!!
//##										;
//##										; Basically what this class does - it uses none blocking SSH2 streams for transfer
//##										; with some little hacks to allow the completion check to complement missing PHP functionality.
//##										; Class is capable sending single file over multiple streams ....and re-use established connections for consequent multi-file transfers
//##										;
//##--------------------;-----------------------------------------------------------------------------------------------------------------------------------------------
//##		[NOTES]					; most of the notes moved to the code it self...
//##										;
//##										; and .....as for anyone who wonders why i called single block transfer as kick ...coz this game between script and buggy PHP reminds me a ball kicking...
//##										; script kicks the ball (data block) to SSH stream ...it kicks it back by rejecting it ...script will continue kicking it till voila ...goal ...block accepted for transfer...
//##										; would be fucking much easier if there were event mechanism that would work ...as with normal streams....
//##										; if you have problems with my name conventions - ...i sure am can write another page of text  - but you already know where i'm headding at with this line of text ....dont you?
//##--------------------;-----------------------------------------------------------------------------------------------------------------------------------------------
//##		[To Do]					;
//##										; - implement directory up/download ....
//##--------------------;-----------------------------------------------------------------------------------------------------------------------------------------------
//##		[Changes:]			; 23/11/2015 - Version 1.0.0
//##										; - Multi steam file upload implemented.
//##										;
//##										; 30/11/2015 - Version 1.1.0
//##										; - Multi steam file download implemented.
//##										;
//##										; 01/12/2015 - Version 1.2.0
//##										; - Multi file transfer over already established connections implemented (up/down)
//##										;
//##										;	02/12/2015 - Version 1.2.1
//##										; - Added dynamic block size limit to accommodate multi-steam transfer for smaller files (smaller than TOP block limit - 8/16KB)
//##										;		files that are FILE_SIZE<(STREAM_COUNT*2) ...will be sent via single stream.
//##										; - Added dynamic block limit size switch at the EOF (large files and newer versions of PHP (5.3+) issue with internal buffers)
//##										;
//##										;	04/12/2015 - Version 1.2.2
//##										; - All uploads now transfered with "local stream offset + chunk size" parameters to avoid fucking up local stream pointer at the EOF (issue for all versions of PHP after 5.3)
//##										; - All (5.3, 5.4, 5.5, 5.6) versions of PHP were tested and timed for speed and "EOF behaviour" issue.
//##										;
//##										;	05/12/2015 - Version 1.2.2
//##										; - Files smaller than 8KB tested...- works like a Swatch !!!.....
//##										;	- Created a Release version. (majority of fucks and shits removed from script)
//##										;
//##										;

//#######################################################################################################################################################################
define('PHP_SSH2MST_SINGLE_KICK_DELAY_DOWNLOAD'				,10000); 			//X ms delay between each iteration on all streams...
define('PHP_SSH2MST_SINGLE_KICK_DELAY_UPLOAD'					,10000); 			//X ms delay between each iteration on all streams...

																																		//Here are some measured values for uploading a single file (44Mb .zip):
																																		//------------------------------------------------------------------------
																																		//	System setup:
																																		//  	- Source:
																																		//						Host:			Leaseweb (DE) Dedicated server located in Frankfurt
																																		//						OS:				Windows Server 2008 Server R2
																																		//						Memory:		16GB
																																		//						CPU:			Intel Xeon E3 1220 @ 3.10Mhz
																																		//						Network:	1Gbit (200mbit guaranteed badnwidth)
																																		//		- Destination:
																																		//						Host:			Edis (CH) OVZ server located in Zurich
																																		//						OS:				Linux Debian 7 (wheezy)
																																		//						Memory:		2Gb
																																		//						CPU:			Intel Xeon E5-2630 v2 @ 2.60GHz (2 cores available)
																																		//						Network:	1Gbit (unlimited)
																																		//		- Script config:
																																		//						Streams:	10
																																		//						Buffer: 	8KB (see notes for "PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_UPLOAD" constant)
																																		//------------------------------------------------------------------------
																																		//All Tests performed after office hours (after 20:00 European time ...when total load on network subsided)
																																		//------------------------------------------------------------------------
																																		//5ms		=> 960KB/sec
																																		//4ms		=> 1.1MB/sec (speed graph - straight line)
																																		//3ms		=> 1.2MB/sec (speed graph - straight line)
																																		//2ms		=> 1.5MB/sec (speed graph - straight line...but script is almost uncontrollable :)...CTRL/C doestnt work as easy as expected )
																																		//1ms		=> 1.8MB/sec (speed graph - straight line...beware of warewolf ..i mean not being able to stop the script)
																																		//0.5ms	=> 2.0MB/sec (speed graph - jaggy eddged line......stopping the script is not possible now over RemoteDesktop connection)
																																		//0.1ms	=> 2.0MB/sec Max (Speed graph - deep drops down to 1.8 average keeps at 2.0MB/sec....)
																																		//No delay - NOT RECOMMENDED !!! - 2.1MB/sec Max (Speed graph - deep drops down to 1.6Mb/sec.... average at 1.9MB/sec)
																																		//------------------------------------------------------------------------
																																		//CPU usage on both computers never exceeded 5% during any of the transfers.....
																																		//------------------------------------------------------------------------
																																		//Minor note - all measurements were made with DEBUG flag set to ON...producing thousands of debug messages.
																																		//without any debug info being printed - script performance about 150% higher...(what was 2.0MB/sec...will go up to 3.0MB/sec...roughly calculated)
																																		//------------------------------------------------------------------------

define('PHP_SSH2MST_SIZECHECK_MAX_ITERATIONS'					,1000);				//this will limit all filesize checking loops....
																																		//coz we have absolutely no control over SSH2 streams ....our one and the only way to not fall into infinite loop is to limit it...
																																		//This might be bit too much ...since i've already implemented "last kick" that would switch stream to blocking mode for last few bytes...
																																		//But still - nobody likes infinite loops ...so ..i'll keep it in use. (never trust PHP streams ....especially on versions older than 5.3)

define('PHP_SSH2MST_FILE_COPY_BLOCK'									,1024*64);		//internal file copy block size...(used when combining downloaded chunks)
																																		//this one is free to be ajusted up to the allowed memory size...- it wont have any effect on speed ....local file copy...)
//#######################################################################################################################################################################
define('PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_UPLOAD'			,(1024*8));	//Tricky one...
																																		//PHP 5.3		You CAN Keep it at 16kb (during whole transfer) ....there will be no oversized chunks .... higher than 16kb will cause "stream_copy_to_stream" to return ALWAYS "0" ...even if it actually did copied something....Just don't :)...
																																		//PHP 5.4		You may ...:) set it to 16kb ...but there will be up to 64kb overhead at the end of each chunk....for all versions after 5.3 ...better keep this at 8kb...and make Kick delay smaller...
																																		//PHP 5.5		Same shit as 5.4...
																																		//PHP 5.6		Same shit as 5.4...
																																		//
																																		//---------------------------------------------------
																																		//BUT ..(note the capital letters :)
																																		//setting bufer to 16kb ... gives quite a boost...(As an example 2ms delay)... gives following results:
																																		//PHP 5.3		2.3MB/sec ...NO overhead !!!! (none of the chunks oversized)
																																		//PHP 5.4		2.3MB/sec ...all chunks are oversized ..(some are 16kb some 40kb ...MAX overhead for each chunk is about 57KB...NEVER above 64KB..as I were expecting...)
																																		//PHP 5.5		2.3MB/sec ...all chunks are oversized ....speed tops out at 2.4MB/sec...average is 2.3MB/sec...(graph line is juggy but small/short fall downs to 2.2MB/sec)
																																		//PHP 5.6		2.4MB/sec ...all chunks are oversized ....speed tops out at 2.5MB/sec...average is 2.4MB/sec...surprisingly very stable graph line....

																																		//Thus ...by choosing 16Kb block - you may gain the speed...but it also introduces up to 64KB per stream overhead that will be transmitted...
																																		//with 10 streams - there will be up to 640KB extra transmitted .... but with much higher speed...
																																		//Large files sure would profit from 16KB buffer...

																																		//Oh.... Totally forgot to mention .....
																																		//oversized chunks ....even after truncating ....and combining
																																		//resulting file is ALWAYS corrupt .....WITH ALL VERSIONS 5.4 AND HIGHER....you are welcome...!!!


//2 sizes for block limit ...this was an attempt to minimize delay of blocking call..(8KB or 16KB...).... but it does not make much of a difference...
//If blocking call blocks - it blocks ..tested with single stream - same results....
//i guess its just network ...(problem with PHP SSH2 implemnetation that it does not offer any control over it self)
//
define('PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_UPLOAD1'			,PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_UPLOAD);
define('PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_UPLOAD2'			,(1024*8));//last kicked block will be set to this size....


define('PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_DOWNLOAD'		,1024*8);			//Keep it at 8kb ....download stream seems to be more conservative and much more stable in this sence ...it will never give you more than 8KB
																																		//Nor it will overflow your chunk ...- all downloaded chunks are exact size as requested...
//#######################################################################################################################################################################

define('PHP_SSH2MST_MULTISTREAM_TMP_FILE_SUFFIX'			,'_CHUNK_');
define('PHP_SSH2MST_MULTISTREAM_TMP_FILE_RANDOM'			,0);					//use random names for tmp file....(disable it for debugging) ...or ...keep it OFF ...this way resumed file chunks will be overwritten.....
																																		//When this option is ON ..and transfer would fail - you'll be left whith bunch of absolutely random named file chunks .....undeleted. (also good for debugging)

define('PHP_SSH2MST_TRANSFER_STATUS_FAILED'						,-1); 				//transfer failed...
define('PHP_SSH2MST_TRANSFER_STATUS_INACTIVE'					,0); 					//transfer not started yet...
define('PHP_SSH2MST_TRANSFER_STATUS_ACTIVE'						,1); 					//transfer is active
define('PHP_SSH2MST_TRANSFER_STATUS_COMPLETE'					,2); 					//transfer is compelete (successfully)



//-------------------------------------------------------------------------------
//some other parameters...
define('PHP_SSH2MST_DEBUG'														,0);					//Set to 1 to enable printing every step of the copy process...(NOTE: there will be alot of text lines....:)....) .....(beware of low sleep delays....- you wont be able to kill your script if delay is lower than 1ms...hehe...)
																																		//do not enable this option for large files ...(choose something moderate ...like 10-100mb filesize....or you'll get flooded....forever :) ...)


define('PHP_SSH2MST_REUSE_RANDOM_CONNECTION'					,0);					//If set - class will reuse randomly chosen stream...if disabled - last deactivated stream is given...
																																		//Difference is that - by using last deactivated stream - there would be virtually no delay in stream activity ....
																																		//effectively corrupting integrity of last transfered chunk....(for someone who would try to assemble streams into single file.....like DPI systems...)

define('PHP_SSH2MST_RANDOMIZE_CHUNK_SIZE'							,0); 					//Randomize the chunk size ....(we'll try anyting to confuse DPI systems as much as possible)
define('PHP_SSH2MST_RANDOM_CHUNK_SIZE_WINDOW'					,10); 				//In percent ...how much size will vary from originally computed chunk size....(up and down)

//#######################################################################################################################################################################
//#######################################################################################################################################################################
//Sort streams array by rejection count or by time if both are equal.
//array is sorted in reverse order - largest rejection count first ...or oldest time...
function DSResort($a,$b)
{
	//first sort by rejection count...
	if($a->reject_counter > $b->reject_counter) return -1;
	if($a->reject_counter < $b->reject_counter) return 1;

	//if all the same - sort by time since last block accepted...- oldest first...
	if($a->last_measure_time > $b->last_measure_time) return -1;
	if($a->last_measure_time < $b->last_measure_time) return 1;
	return 0;
}
//#######################################################################################################################################################################
//Restore streams array to original order before calling assembly function...
function DSResortOriginal($a,$b)
{
	if($a->id > $b->id) return 1;
	if($a->id < $b->id) return -1;
	return 0;
}
//#######################################################################################################################################################################
//Sort inactive streams by time...(last used first)
function DSResortLastUsed($a,$b)
{
	if($a->last_used > $b->last_used) return -1;
	if($a->last_used < $b->last_used) return 1;
	return 0;
}
//########################################################################
//########################################################################
//#####                                                                ###
//#####               PHP_SSH2MST                                      ###
//#####                                                                ###
//########################################################################
//########################################################################
class PHP_SSH2MST
{
	public $host;
	public $port;
	public $user;
	public $pass;
	public $streams;																	//list of initiated transfer streams for a single file..

	public $max_streams										=10;				//number of simultaneous streams for file transfer...
	public $last_file_size								=0;					//will be filled file size for last trasfered file
	public $last_transfer_time						=0;					//will be filled with duration time of last file transfer
	public $last_transfer_speed						=0;					//will be filled with speed value after transfer completed. (computed over whole transfer...it may differ from combined speed due to our own delays...or delays introduced by PHP...)
	public $last_combined_transfer_speed	=0;					//average ....combined speed of last file transfer ...(summed speed of all streams...(this should be more close to the actual speed)

																										//Note for the speed values...:
																										//If last transfer speed is lower than combined... this actually indicates too high delay value selected for the delay...
																										//you might try to tweak some of them....but making it too small - will introduce HIGH CPU usage during transfer.

	public $disable_chunking							=0;					//internal flag ...will be set by newly created stream to force current file transfer via single stream (file size is just too small)
//----------------------------------------------------------------------
	private $connections_cache_active			=array(); 	//list of active (taken by some of the streams) connections...
	private $connections_cache_passive		=array(); 	//list of inactive but connected connections....after each stream completes - connection is moved into this list..
	private $host_fingerprint							='';
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//if $host_fingerprint is specified...and on connection it would missmatch with received one - transfer will fail.
	public function __construct($host,$port,$user,$pass,$host_fingerprint='')
	{
		$this->host=$host;
		$this->port=$port;
		$this->user=$user;
		$this->pass=$pass;

		$this->ssh_methods=array(
															'kex'								=> 'diffie-hellman-group1-sha1',
															'client_to_server'	=> array(
																														'crypt' => '3des-cbc',
																														'comp' => 'none'
																													),
															'server_to_client'	=> array(
																														'crypt' => 'aes256-cbc,aes192-cbc,aes128-cbc',
																														'comp' => 'none'
																													)
														);
		$this->ssh_callbacks=array(
																//NONE OF THIS CRAP WORKS WITH SSH2 CONNECTION .....
																//NOT IN class::function form ...NOT AS SINGLE FUNCTION !!! ...
																//
																//Feel free to prove me wrong !!!...

//														'ignore'			=> array($this, '_ssh_callback_Ignore'),
//														'debug'				=> array($this, '_ssh_callback_Debug'),
//														'macerror'		=> array($this, '_ssh_callback_MACerror'),
//														'disconnect'	=> array($this, '_ssh_callback_Disconnect'),

//														'ignore'			=> '_ssh_callback_Ignore',
//														'debug'				=> '_ssh_callback_Debug',
//														'macerror'		=> '_ssh_callback_MACerror',
//														'disconnect'	=> '_ssh_callback_Disconnect',
															);
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
  public function __destruct()
  {
  	//something should be done here....
  	//but usually not - all SSH connections terminated on exit automatically...
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
	function getMicroTime()
	{
		list($usec, $sec) = explode(" ",microtime());
		return ((float)$usec + (float)$sec);
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//Connect and login into remote server...
//return std class with created handlers...
	public function getSSH2Connection()
	{
		//try to get any passive connection ...if any...
		if (count($this->connections_cache_passive))
		{
			if (count($this->connections_cache_passive)==1)
			{
				//just return one...(array is never zero based ....)
				$connection_data=array_shift($this->connections_cache_passive); //get one and remove it from passive...
			}else
			{
				//we got more to choose from ....
				if (PHP_SSH2MST_REUSE_RANDOM_CONNECTION)
				{
					//get randomly one out all available passive connections...
					//the more we shuffle our data among all those connections - the more difficult it will be for those suckers that installed DPI in they centers...
					$i=array_rand($this->connections_cache_passive,1);
				}else
				{
					//return last used stream..this will effectively break the file integrity if assembled as-is...by DPI...
					//unless of coz ... last used connection was the last stream.... in this case we'll randomize...
					usort($this->connections_cache_passive,'DSResortLastUsed');
					$i=0;
					if ($this->connections_cache_passive[$i]->connection_index==$this->max_streams)
					{
						//crap ... it is the last one. :)
						//keep randomizing till we'll get something else...
						$radom_enough=0;
						while(!$radom_enough)
						{
							$i=array_rand($this->connections_cache_passive,1);
							if ($this->connections_cache_passive[$i]->connection_index!=$this->max_streams)
							{
								$radom_enough=1;
							}
						}
					}
				}
				$connection_data=$this->connections_cache_passive[$i];
				//and remove from passive...
				unset($this->connections_cache_passive[$i]);
			};
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "|||||||||| REUSING EXISTING CONNECTION .....$connection_data->connection_index..\n";
//---------------------------------------------------------------------------------------------------------------
			//save in active list...
			$this->connections_cache_active[]=$connection_data;
			return $connection_data;
		}
		//nothing cached - create new connection...
		$connection_data=new stdClass();
		$connection_data->connection_index=			count($this->connections_cache_active)+1;
		$connection_data->connected							=0;
		$connection_data->connection_handle			=NULL;
		$connection_data->ssh_base_dir					='';
		$connection_data->sftp_handle						=NULL;
		$connection_data->last_used							=$this->getMicroTime();
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Creating SSH Connection {$connection_data->connection_index}\n";
//---------------------------------------------------------------------------------------------------------------
		if ($connection_data->connection_handle = ssh2_connect($this->host,$this->port,$this->ssh_methods,$this->ssh_callbacks))
		{
			if (ssh2_auth_password($connection_data->connection_handle, $this->user, $this->pass))
			{
				if ($connection_data->sftp_handle = ssh2_sftp($connection_data->connection_handle))
				{
					if ($this->host_fingerprint)
					{
						if ($this->host_fingerprint != ssh2_fingerprint($connection_data->connection_handle ,SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX))
						{
							$this->error='Wrong Server Fingerprint.';
							$this->disconnect();
						}
					}
					$connection_data->ssh_base_dir='ssh2.sftp://' . $connection_data->sftp_handle;
					$connection_data->connected=1;
				}else
				{
					$this->error='Unable to get SFTP subsystem handle.';
				}
			}else
			{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "SSH authentication failed....\n";
//---------------------------------------------------------------------------------------------------------------
				$this->error='Authentication failed.';
			}
		}else
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "SSH connection failed\n";
//---------------------------------------------------------------------------------------------------------------
			$this->error='Unable to connect to remote server.';
		}
		//save in active list...
		$this->connections_cache_active[]=$connection_data;
		return $connection_data;
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
	public function downloadFile($local_file,$remote_file)
	{
		$connection_data=$this->getSSH2Connection(); //Create first connection - it will be reused later...
		if (!$connection_data->connected)
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Connection failed...\n";
//---------------------------------------------------------------------------------------------------------------
			$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
			return 0;
		}
		$rf=$connection_data->ssh_base_dir . '/' . $remote_file;

		//release the connection - we dont need it anymore...(it still active though)
		$this->ReleaseConnection($connection_data);

		if ($this->last_file_size=filesize($rf))
		{
			//time the transfer for speed measure...
			$this->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_INACTIVE;
			$this->last_transfer_time=$this->getMicroTime();
			$this->streams=array();
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "File size=	$this->last_file_size\n";
//---------------------------------------------------------------------------------------------------------------
			if ($this->max_streams>1)
			{
				//multi stream....split in chunks....
				$chunk_offset=0;
				$chunk_size=floor($this->last_file_size/$this->max_streams);
				//if chunk is too freaking small - send via single stream...
				if ($chunk_size<$this->max_streams*2) return $this->downloadFile_Single_Stream($local_file,$remote_file);
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Computed Chunk Size=	$chunk_size\n";
//---------------------------------------------------------------------------------------------------------------
				//create all required streams
				$coco_mango=$this->max_streams-1;
				for ($c=0;$c<$this->max_streams;$c++)
				{
					if (PHP_SSH2MST_RANDOMIZE_CHUNK_SIZE)
					{
						$used_chunk_size=$chunk_size+round($chunk_size*(rand(-PHP_SSH2MST_RANDOM_CHUNK_SIZE_WINDOW,PHP_SSH2MST_RANDOM_CHUNK_SIZE_WINDOW)/100));
					}else
					{
						$used_chunk_size=$chunk_size;
					}
					if ($c==$coco_mango) $used_chunk_size=$this->last_file_size-$chunk_offset; //let the last chunk take all remaining bytes...
					//create new stream for current chunk ...(it will create its own connection)
					if (PHP_SSH2MST_MULTISTREAM_TMP_FILE_RANDOM)
					{
						$file_name=preg_replace('/[^\/\\\]+$/',sha1(uniqid()),$local_file);
					}else
					{
						$file_name=$local_file . PHP_SSH2MST_MULTISTREAM_TMP_FILE_SUFFIX . $c;
					}
					$transfer_stream=new PHP_SSH2MSTSDown($this,$c,$remote_file,$file_name,$chunk_offset,$used_chunk_size);

					//if at any time streaming class would request to disable chunking...
					//we'll fall back to single stream mode...
					if ($this->disable_chunking) return $this->downloadFile_Single_Stream($local_file,$remote_file);

					if ($transfer_stream->isConnected())
					{
						$this->streams[$c]=$transfer_stream;
						$chunk_offset+=$used_chunk_size;
					}else
					{
						//failed ...
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "STREAM $c IS NOT CONNECTED\n";
//---------------------------------------------------------------------------------------------------------------
						$this->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
						return false;
					}
				}
				//now keep randomizing array till we got first stream mixed somewhere inbetweeen
				$this->ShuffleStreamsBeforeFirstRun();

//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Entering Main Loop\n";
//---------------------------------------------------------------------------------------------------------------

				$ALL_STREAMS_current_kick_status=1;
				while (
								$ALL_STREAMS_current_kick_status &&
								$this->transfer_status!=PHP_SSH2MST_TRANSFER_STATUS_COMPLETE &&
								$this->transfer_status!=PHP_SSH2MST_TRANSFER_STATUS_FAILED
							)
				{
					$ALL_STREAMS_current_kick_status=0;
					$combined_speed=0;
					foreach ($this->streams as $si=>$transfer_stream)
					{
						$ALL_STREAMS_current_kick_status|=$transfer_stream->TickTack_Download();
						$combined_speed+=$transfer_stream->last_transfer_speed;
					}
					//compute average speed...
					if ($this->last_combined_transfer_speed)
					{
						$this->last_combined_transfer_speed+=$combined_speed;
						$this->last_combined_transfer_speed/=2;
					}else
					{
						$this->last_combined_transfer_speed=$combined_speed;
					}
					if (PHP_SSH2MST_SINGLE_KICK_DELAY_DOWNLOAD) usleep(PHP_SSH2MST_SINGLE_KICK_DELAY_DOWNLOAD);
					//resort streams.....and place streams with highest reject count on top....
					usort ($this->streams,'DSResort');
				};
				if ($this->AssembleDownloadFile($local_file))
				{
					$this->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_COMPLETE;
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n^^^___^^^___FILE TRANSFERRED SUCCESSFULLY !!!^^^___^^^___\n";
//---------------------------------------------------------------------------------------------------------------
				}else
				{
					//failed....
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n_____________FILE TRANSFER FAILED___________\n";
//---------------------------------------------------------------------------------------------------------------
					$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
					return 0;
				}
				if ($this->transfer_status==PHP_SSH2MST_TRANSFER_STATUS_COMPLETE)
				{
					//compute some speed stats...
					$this->last_transfer_time=$this->getMicroTime()-$this->last_transfer_time;
					if ($this->last_transfer_time)
					{
						$this->last_transfer_speed=$this->last_file_size/$this->last_transfer_time;
					}else $this->last_transfer_speed=0;
					return true;
				};
			}else
			{
				return downloadFile_Single_Stream($local_file,$remote_file);
			}
		}else
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Failed Opening Remote FIle\n";
//---------------------------------------------------------------------------------------------------------------
			$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
			return 0;
		}
	}
//###############################################################################################################################################################################################################
	public function downloadFile_Single_Stream($local_file,$remote_file)
	{
		$connection_data=$this->getSSH2Connection();
		if (!$connection_data->connected)
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n_____________FILE TRANSFER FAILED___________\n";
//---------------------------------------------------------------------------------------------------------------
			$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
			return 0;
		}
		$res=ssh2_scp_recv($connection_data->connection_handle,$remote_file,$local_file);
		//release the connection - we dont need it anymore
		$this->ReleaseConnection($connection_data);
		return $res;
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
	//returns true on success
	public function uploadFile($remote_file,$local_file)
	{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "UPLOADING FILE =	$local_file\n";
//---------------------------------------------------------------------------------------------------------------
		if ($this->last_file_size=filesize($local_file))
		{
			$this->streams=array();
			$this->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_INACTIVE;
			//time the transfer for speed measure...
			$this->last_transfer_time=$this->getMicroTime();
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "File size=	$this->last_file_size\n";
//---------------------------------------------------------------------------------------------------------------
			if ($this->max_streams>1)
			{
				//multi stream....split in chunks....
				$chunk_offset=0;
				$chunk_size=floor($this->last_file_size/$this->max_streams);
				//if chunk is too freaking small - send via single stream...
				if ($chunk_size<$this->max_streams*2) return $this->uploadFile_Single_Stream($remote_file,$local_file);
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "||||||||||| Computed Chunk Size=	$chunk_size ||||||||\n";
//---------------------------------------------------------------------------------------------------------------
				//create all required streams
				for ($c=0;$c<$this->max_streams;$c++)
				{
					if (PHP_SSH2MST_RANDOMIZE_CHUNK_SIZE)
					{
						$used_chunk_size=$chunk_size+round($chunk_size*(rand(-PHP_SSH2MST_RANDOM_CHUNK_SIZE_WINDOW,PHP_SSH2MST_RANDOM_CHUNK_SIZE_WINDOW)/100));
					}else
					{
						$used_chunk_size=$chunk_size;
					}
					//let the last chunk take all remaining bytes...
					if ($c==$this->max_streams-1)
					{
						$used_chunk_size=$this->last_file_size-$chunk_offset;
					}
					//create new stream for current chunk ...(it will create its own connection)
					if (PHP_SSH2MST_MULTISTREAM_TMP_FILE_RANDOM)
					{
						$file_name=preg_replace('/[^\/\\\]+$/',sha1(uniqid()),$remote_file);
					}else
					{
						$file_name=$remote_file . PHP_SSH2MST_MULTISTREAM_TMP_FILE_SUFFIX . $c;
					}
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "######################################### creating STREAM CLASS $c\n";
//---------------------------------------------------------------------------------------------------------------
					$transfer_stream=new PHP_SSH2MSTSUp($this,$c,$file_name,$local_file,$chunk_offset,$used_chunk_size);
					if ($c==0) $transfer_stream->first_stream=1;

					//if at any time streaming class would request to disable chunking...
					//we'll fall back to single stream mode...
					if ($this->disable_chunking) return $this->uploadFile_Single_Stream($remote_file,$local_file);
					if ($transfer_stream->isConnected())
					{
						$this->streams[$c]=$transfer_stream;
						$chunk_offset+=$used_chunk_size;
					}else
					{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "||||||||||| STREAM $c CONNECTION FAILED ||||||||\n";
//---------------------------------------------------------------------------------------------------------------
						//failed ...
						$this->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
						return false;
					}
				}
				$transfer_stream->last_stream=1;

				//now keep randomizing array of streams......... till we get first stream mixed somewhere inbetweeen
				$this->ShuffleStreamsBeforeFirstRun();

				//at this moment - all streams have own connection ...and ready for transfer...
				//now we'll have to run trough all of them and offer a data block untill all is transfered....
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Entering Main Loop [$this->transfer_status]\n";
//---------------------------------------------------------------------------------------------------------------
				$ALL_STREAMS_current_kick_status=1;
				while (
								$ALL_STREAMS_current_kick_status &&
								$this->transfer_status!=PHP_SSH2MST_TRANSFER_STATUS_COMPLETE &&
								$this->transfer_status!=PHP_SSH2MST_TRANSFER_STATUS_FAILED
							)
				{
					$ALL_STREAMS_current_kick_status=0;
					$combined_speed=0;
					foreach ($this->streams as $si=>$transfer_stream)
					{
						$ALL_STREAMS_current_kick_status|=$transfer_stream->TickTack_Upload();
						$combined_speed+=$transfer_stream->last_transfer_speed;
					}
					//compute average
					if ($this->last_combined_transfer_speed)
					{
						$this->last_combined_transfer_speed+=$combined_speed;
						$this->last_combined_transfer_speed/=2;
					}else
					{
						$this->last_combined_transfer_speed=$combined_speed;
					}
					usleep(PHP_SSH2MST_SINGLE_KICK_DELAY_UPLOAD);
					//resort streams.....and place streams with highest reject count on top....
					usort ($this->streams,'DSResort');
				};
				if ($this->transfer_status!=PHP_SSH2MST_TRANSFER_STATUS_FAILED)
				{
					if ($this->AssembleUploadFile($remote_file))
					{
						$this->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_COMPLETE;
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n^^^___^^^___FILE TRANSFERRED SUCCESSFULLY !!!^^^___^^^___\n";
//---------------------------------------------------------------------------------------------------------------
					}else
					{
						//nop ...failed....
						$this->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_COMPLETE;
					}
				}
				if ($this->transfer_status==PHP_SSH2MST_TRANSFER_STATUS_COMPLETE)
				{
					//compute some speed stats...
					$this->last_transfer_time=$this->getMicroTime()-$this->last_transfer_time;
					if ($this->last_transfer_time)
					{
						$this->last_transfer_speed=$this->last_file_size/$this->last_transfer_time;
					}else $this->last_transfer_speed=0;
					return true;
				};
				return false; //nop ...totally failed..
			}else
			{
				return $this->uploadFile_Single_Stream($remote_file,$local_file);
			}
		}else
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Failed Opening Local FIle\n";
//---------------------------------------------------------------------------------------------------------------
			$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
			return 0;
		}
	}
//###############################################################################################################################################################################################################
	public function uploadFile_Single_Stream($remote_file,$local_file)
	{
		//just transfer ...no splitting
		$connection_data=$this->getSSH2Connection(); //we just need one conection ....reuse if anything present....
		if (!$connection_data->connected)
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n_____________FILE TRANSFER FAILED___________\n";
//---------------------------------------------------------------------------------------------------------------
			$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
			return 0;
		}
		$res=ssh2_scp_send ($connection_data->connection_handle,$local_file, $remote_file ,0644);
		//release the connection - we dont need it anymore
		$this->ReleaseConnection($connection_data);
		return $res;
	}
//###############################################################################################################################################################################################################
//To avoid transfering first stream as very first ...
//We'll randomize the array of streams untill first stream mixed somewhere inbetween...
//This should make it more difficult for DPI systems that would try to reassemble streams on the fly for decryption....
	private function ShuffleStreamsBeforeFirstRun()
	{
		$random_enough=0;
		while(!$random_enough)
		{
			shuffle($this->streams);
			if ($this->streams[0]->id!=0) $random_enough=1; //done...
		}
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//finalizing the transfer.....
//combine all tranfered files into single one...
	private function AssembleUploadFile($remote_file)
	{
		//resort streams back to original order....
		usort($this->streams,'DSResortOriginal');

		$connection_data=$this->getSSH2Connection(); //get any connection available ...(reusing existing one..)
		if (!$connection_data->connected)
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n_____________FILE TRANSFER FAILED___________\n";
//---------------------------------------------------------------------------------------------------------------
			$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
			return 0;
		}
		//remove the destination if exists....
		$df=$connection_data->ssh_base_dir . '/' . $remote_file;
		if (file_exists($df)) unlink($df);

		$cmd='';
		foreach ($this->streams as $si=>$transfer_stream)
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Combining..........$transfer_stream->remote_file =>$transfer_stream->chunk_size bytes...\n";
//---------------------------------------------------------------------------------------------------------------
			if ($transfer_stream->oversized>0)
			{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Chunk is oversized by $transfer_stream->oversized bytes\n";
//---------------------------------------------------------------------------------------------------------------
				//stream has sent too much ...".....you took to much ...you took too much man ..(Fear and Loathing in Las Vegas))...:)
				//truncate it to required size ...combine and remove...
				$scmd='truncate -s ' . $transfer_stream->chunk_size . ' ' . $transfer_stream->remote_file . ';cat ' . $transfer_stream->remote_file . ' >> ' . $remote_file . '; rm ' . $transfer_stream->remote_file;
			}else
			{
				//good stream ...completed correctly.....just combine and remove
				$scmd='cat ' . $transfer_stream->remote_file . ' >> ' . $remote_file . '; rm ' . $transfer_stream->remote_file;
			};
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo $scmd . "\n";
//---------------------------------------------------------------------------------------------------------------
			$cmd.=$scmd.';';
		};
		//All in single command...
		ssh2_exec($connection_data->connection_handle, $cmd);
		//release the connection - we dont need it anymore
		$this->ReleaseConnection($connection_data);
		return 1;
	}
//###############################################################################################################################################################################################################
//finalizing the transfer.....
//combine all tranfered files into single one...
	private function AssembleDownloadFile($local_file)
	{
		//resort streams back to original order....
		usort($this->streams,'DSResortOriginal');

		//remove the destination if exists....
		if (file_exists($local_file)) unlink($local_file);
		if ($dh = fopen($local_file, 'w'))
		{
			foreach ($this->streams as $si=>$transfer_stream)
			{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Combining..........$transfer_stream->local_file =>$transfer_stream->chunk_size bytes...\n";
//---------------------------------------------------------------------------------------------------------------
 	       if ($sh = fopen($transfer_stream->local_file, 'r'))
 	       {
		        while (!feof($sh))
		        {
 	           if (fwrite($dh, fread($sh, PHP_SSH2MST_FILE_COPY_BLOCK)) === FALSE)
 	           {
 	           	return 0;
 	           }
						}
	 	       fclose($sh);
					//remove chunk file...
					unlink($transfer_stream->local_file);
 	       }else
 	       {
					fclose($dh);
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Combining failed - unable to open chunk $si\n";
//---------------------------------------------------------------------------------------------------------------
					return 0; //failed...
 	       }
			};
			fclose($dh);
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "Combining file - OK\n";
//---------------------------------------------------------------------------------------------------------------
			return 1; //all went fine...
		}
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################

//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################

//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
	public function bytes2string( $size )
	{
	   $count = 0;
	   $format = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
	   while(($size/1024)>1 && $count<8)
	   {
	       $size=$size/1024;
	       $count++;
	   }
	   if( $size < 10 ) $decimals = 1;
	   else $decimals = 0;
	   $return = number_format($size,$decimals,'.',' ')." ".$format[$count];
	   return $return;
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
	function ReleaseConnection($connection_data)
	{
		//remove from active list...
		foreach ($this->connections_cache_active as $ci=>$cd)
		{
			if ($cd->connection_index==$connection_data->connection_index)
			{
				unset($this->connections_cache_active[$ci]);
				break;
			}
		}
		//save in passive list...
		$connection_data->last_used=$this->getMicroTime();
		$this->connections_cache_passive[]=$connection_data;
	}
}
//########################################################################
//########################################################################
//#####                                                                ###
//#####               PHP_SSH2MSTSBase                                 ###
//#####                                                                ###
//########################################################################
//########################################################################
//single stream class for Remote File Manager class
class PHP_SSH2MSTSBase
{
	public $id;
	public $transfered_bytes;
	public $chunk_size;
	public $chunk_offset;
	public $handle_stream_locall;
	public $handle_stream_remote;

	public $last_transfer_speed		=0;			//will be populated with speed in bytes of last transfered block
	public $last_block_size				=0;			//will be populate with size of last accepted for transfer block
	public $last_block_time				=0;			//will be populate with time it took to transfer last block (in microseconds)

	public $last_measure_time			=0;			//in microseconds - time of last acceped block.
	public $reject_counter;								//will contain number of rejects since last accepted block....
																				//it will be used to resort stream array to put streams with most rejects on top of the list...
																				//so that next "kick" itteration - those will be processed as first....
	public $active								=0;			//set to 1 as long stream is buzy with transfer
	public $failed								=0;			//for tracing only ....will be set when single stream fails ....(one fails - ALL fails - transfer will be terminated)

	public $oversized							=0;			//SSH2 streams may be very un-controllable ...PHP versions after 5.3 ... are too kind ...they will upload bunch of junk at the end of requested chunk...
																				//quite alot actually ...up to 64kb of fucking junk...(not 5.3 versions though !!!!!)
																				//-----
																				//this flag will indicate that resulting file on remote server MUST be truncated before re-assembly.


	public $first_stream					=0;
	public $last_stream						=0;
//=====================================
	protected $parent							=NULL;
	protected $connection_data		=NULL;
	protected $block_top_limit;
	protected $max_block_size;
//###############################################################################################################################################################################################################
//############################################################################wtf###################################################################################################################################
//###############################################################################################################################################################################################################
//if $host_fingerprint is specified...and on connection it would missmatch with received one - connection will be terminated...
	public function __construct($parent,$id,$chunk_offset,$chunk_size,$block_top_limit)
	{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "...Chunk Size $chunk_size\n";
//---------------------------------------------------------------------------------------------------------------

		$this->parent									=$parent;
		$this->connection_data				=$this->parent->getSSH2Connection();
		$this->chunk_size							=$chunk_size;
		$this->chunk_offset						=$chunk_offset;
		$this->id											=$id;
		$this->block_top_limit				=$block_top_limit;


		if ($this->connection_data->connected)
		{
			//compute max block size...that will be used for single data kick ...
			//...
			//basically - its just max limit set for all transfers....
			//in case of small files - limit - will be adjusted ..to allow at least 2 blocks to be sent via each steam ...
			if ($chunk_size)
			{
				if ($chunk_size>($this->block_top_limit*2))
				{
					//we are fine... got required 2 blocks of max size....
					$this->max_block_size					=$this->block_top_limit;
				}else
				{
					//nop ...chunk is too small
					//we'll try gradually decrease the limit size...(cutting limit in half each iteration ....for files this small keeping it as large as possible wont make much of a difference)
					//our goal is to split file among all available streams..........no mater what....
					while (($this->block_top_limit=$this->block_top_limit>>1)>1)
					{
						if ($chunk_size>($this->block_top_limit*2))
						{
							//got it ...we can use this limit ...
							$this->max_block_size					=$this->block_top_limit;
							break;
						}
					};
				}
				if (!$this->max_block_size)
				{
					//Fuck ...the file is that small :)
					//at this point - we down to just 1 byte limit...
					//screw this ... we'll send it via one stream...
					$this->parent->disable_chunking=1;
				}
			}
		};
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
	public function isConnected()
	{
		//for now just return saved flag...
		//php does not offer much for this kind of stream...
		return $this->connection_data->connected;
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
	protected function TerminateStreams()
	{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "|||||||||| TERMINATING STREAM.......\n";
//---------------------------------------------------------------------------------------------------------------
		if ($this->handle_stream_local)
		{
			fclose($this->handle_stream_local);
			$this->handle_stream_local=NULL;
		}
		if ($this->handle_stream_remote)
		{
			fclose($this->handle_stream_remote);
			$this->handle_stream_remote=NULL;
		}
		//and save current connection into cache ...for later recycling...
		$this->parent->ReleaseConnection($this->connection_data);
	}
//###############################################################################################################################################################################################################
	protected function CalculateCurrentSpeed()
	{
		//calculate last transfer speed.....
		$mt=$this->parent->getMicroTime();
		if ($this->last_measure_time)
		{
			if ($this->last_block_time=($mt-$this->last_measure_time))
			{
				$this->last_transfer_speed=$this->last_block_size/$this->last_block_time;
			};
		}
		$this->last_measure_time=$mt;
	}
};
//########################################################################
//########################################################################
//#####                                                                ###
//#####               PHP_SSH2MSTSUp                                   ###
//#####                                                                ###
//########################################################################
//########################################################################
//single stream class for Remote File Manager class
class PHP_SSH2MSTSUp extends PHP_SSH2MSTSBase
{
	public 	$remote_file;
	private $size_check_iterations	=PHP_SSH2MST_SIZECHECK_MAX_ITERATIONS; //nobody likes infinite loops :)
	private $last_kick=0;
//###############################################################################################################################################################################################################
//############################################################################wtf###################################################################################################################################
//###############################################################################################################################################################################################################
//if $host_fingerprint is specified...and on connection it would missmatch with received one - connection will be terminated...
	public function __construct($parent,$id,$remote_file,$local_file,$chunk_offset,$chunk_size)
	{
		parent::__construct($parent,$id,$chunk_offset,$chunk_size,PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_UPLOAD1);
		if ($this->connection_data->connected)
		{
			$this->remote_file						=$remote_file;
			$this->transfered_bytes				=0;
			$this->current_chunk_offset		=$chunk_offset;

			if ($this->handle_stream_local= fopen($local_file, 'r'))
			{
				//fseek($this->handle_stream_local,$chunk_offset); 	//move local file pointer to the chunk offset...
				//the only way so far to open none blocking stream to remote server for writing ....fopen always return blocking stream witch will ignore any attempts to make it unblocking....
				if ($this->handle_stream_remote=ssh2_exec($this->connection_data->connection_handle, 'cat > ' . $remote_file))
				{
					//Yo... so far ...so good...
					$this->active=1;
				}else
				{
					$this->failed=1;
					$this->error='Unable to open remote file';
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "UNABLE TO OPEN REMOTE FILE\n";
//---------------------------------------------------------------------------------------------------------------
					$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
				}
				//fclose($this->handle_stream_remote);
			}else
			{
				$this->failed=1;
				$this->error='Unable to open local file';
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "UNABLE TO OPEN LOCAL FILE\n";
//---------------------------------------------------------------------------------------------------------------
				$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
			}
		}
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//Single Kick for a stream ...
//We'll try to kick the ball .... i mean the block of data ...to the remote stream...
//it will accept it ...or kick it back ....i mean return false...
	public function TickTack_Upload()
	{
		if ($this->active)
		{
//---------------------------------------------------------------------------------------------------------------
//if (PHP_SSH2MST_DEBUG && $this->id==9) echo "Processing STREAM [  $this->id  ]\n";
if (PHP_SSH2MST_DEBUG) echo "Processing STREAM [  $this->id  ]\n";
//---------------------------------------------------------------------------------------------------------------
			if ($this->transfered_bytes < $this->chunk_size)
			{
				$block_size=$this->chunk_size-$this->transfered_bytes;
				$bytes_left=$this->chunk_size-$this->transfered_bytes;
				if ($block_size <= $this->max_block_size)
				{
					if(!$this->last_kick)
					{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n||| LAST BLOCK FOR STREAM $this->id ......... SETTING STREAM TO BLOCKING.(LWB=$this->last_block_size)...(Bytes Left=$bytes_left)...(CTB=$this->transfered_bytes) < (CCS=$this->chunk_size)..\n";
//---------------------------------------------------------------------------------------------------------------
						stream_set_blocking($this->handle_stream_remote,1);
						$this->last_kick=1;
					}
				}else $block_size=$this->max_block_size; //never ask more than max defined bytes.... (read the class description)

				if ($wb=stream_copy_to_stream($this->handle_stream_local,$this->handle_stream_remote,$block_size,$this->current_chunk_offset))
				{
					$this->current_chunk_offset+=$wb;
					$this->CalculateCurrentSpeed();
					$this->transfered_bytes+=$wb;
					$this->last_block_size=$wb;
					$this->reject_counter		=0;
				}else
				{
					$this->reject_counter++;
				}
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG)
{
	$kbs=$this->last_transfer_speed/1024;
	echo "...KICKED..[Rejects = $this->reject_counter]..(sent=$wb)...(TRANSFERED=$this->transfered_bytes) (LEFT=$bytes_left)  (CHUNK SIZE=$this->chunk_size)...(LBT=$this->last_block_time)...SPEED=$kbs Kb/sec\n";
};
//---------------------------------------------------------------------------------------------------------------
			}else
			{
				//all seems to be sent ...- now we'll try to get the size....
				//previous attempts to control SSH2 stream ...we based on this call ...(coz it would always fail as long stream is still transferring)...
				//but current setup is no longer dependable on it ...- last kick will set stream to blocking ....thats enough ..
				//
				//however ...we still need the actual chunk size on remote system ..(plz read the notes on this issue at the top of the script)
				return $this->checkSize();
			}
			return 1; //indicate that we are still up and running....
		}
		//not active ...
		return 0;
	}
//###############################################################################################################################################################################################################
	public function checkSize()
	{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "----------------STREAM $this->id is INACTIVE.... CHECKING SIZE....";
//---------------------------------------------------------------------------------------------------------------
		if ($statinfo=@ssh2_sftp_stat($this->connection_data->sftp_handle, $this->remote_file))
		{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "------GOT SOME....{$statinfo['size']}>=$this->chunk_size";
//---------------------------------------------------------------------------------------------------------------
			if ($statinfo['size']>=$this->chunk_size) //We cannot EVER trust PHP SSH2 streams.... but if we got the "enough bytes" size we need....we can truncate it later...
			{
				//REALLY FUCKED UP ....
				//PHP version 5.3 ... - size is always exact ...
				//ALL PHP VERSIONS ABOVE 5.3......chunks are padded with random junk ... up to 64kb long...it seams that dudes read beyond specified size ..and fucking up the local stream pointer....
				//last stream will fail on all 5.4+ versions ...if only size is specified....(no offset)
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "------FILE SIZE IS OK...........";
//---------------------------------------------------------------------------------------------------------------
				//Yo ... got the size ....and its OK...
				$this->active=0; 							//disable this stream ...
				$this->TerminateStreams();		//and terminate all handlers...

				//but check if we have to truncate it later...
				$this->oversized=$statinfo['size']-$this->chunk_size;
				return 0; //done ...
			}else
			{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "------FAILED ...WRONG SIZE..(Needed $this->chunk_size.... GOT {$statinfo['size']}....";
//---------------------------------------------------------------------------------------------------------------
				//nop....we've got the size...and its smaller...PHP dropped the ball .
				//Whole transfer has failed
				$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
				$this->active=0;
				$this->failed=1;
				$this->TerminateStreams();
				return 0; //done ...
			}
		}else
		{
			//cannot get anything yet....just count iterations...
			$this->size_check_iterations--;
			if (!$this->size_check_iterations)
			{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "------NO MORE ITERATIONS...........";
//---------------------------------------------------------------------------------------------------------------
				//Done ... no size ..no nothing...
				//Whole transfer has failed
				$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
				$this->active=0;
				$this->failed=1;
				$this->TerminateStreams();
				return 0; //done ...
			}else
			{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "------NO INFO YET...........";
//---------------------------------------------------------------------------------------------------------------
			}
		}
		return 1; //indicate that we still active ....no size available yet...
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n";
//---------------------------------------------------------------------------------------------------------------
	}
}
//########################################################################
//########################################################################
//#####                                                                ###
//#####               PHP_SSH2MSTSDown                                 ###
//#####                                                                ###
//########################################################################
//########################################################################
//single stream class for Remote File Manager class
class PHP_SSH2MSTSDown extends PHP_SSH2MSTSBase
{
	public 	$local_file;
//###############################################################################################################################################################################################################
//############################################################################wtf###################################################################################################################################
//###############################################################################################################################################################################################################
//if $host_fingerprint is specified...and on connection it would missmatch with received one - connection will be terminated...
	public function __construct($parent,$id,$remote_file,$local_file,$chunk_offset,$chunk_size)
	{
		parent::__construct($parent,$id,$chunk_offset,$chunk_size,PHP_SSH2MST_MAX_STREAM_BLOCK_SIZE_DOWNLOAD);
		if ($this->connection_data->connected)
		{
			$this->local_file							=$local_file;
			$this->transfered_bytes				=0;

			if ($this->handle_stream_local= fopen($local_file, 'w'))
			{
				//the only way so far to open none blocking stream to remote server for writing ....fopen always return blocking stream witch will ignore any attempts to make it unblocking....
				if ($this->handle_stream_remote=ssh2_exec($this->connection_data->connection_handle, 'dd if="' . $remote_file . '" bs=1 skip=' . $chunk_offset . ' count=' . $chunk_size))
				{
					//Yo... so far ...so good...
					$this->active=1;
				}else
				{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n_____________FILE TRANSFER FAILED___________\n";
//---------------------------------------------------------------------------------------------------------------
					$this->failed=1;
					$this->error='Unable to open remote file';
					$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
				}
			}else
			{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n_____________FILE TRANSFER FAILED___________\n";
//---------------------------------------------------------------------------------------------------------------
				$this->failed=1;
				$this->error='Unable to open local file';
				$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
			}
		}
	}
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
//###############################################################################################################################################################################################################
	public function TickTack_Download()
	{
		if ($this->active)
		{
//---------------------------------------------------------------------------------------------------------------
//if (PHP_SSH2MST_DEBUG) echo "Processing STREAM $this->id\n";
//---------------------------------------------------------------------------------------------------------------
			if ($this->transfered_bytes < $this->chunk_size)
			{
				//executing copy stream untill it returns 0 bytes
				$block_size=$this->chunk_size-$this->transfered_bytes;

				//Here is another hack .... whole transfer stream was non blocking....
				//if we'll pass last block as-is - we wont be able to reuse the connection once its finished....
				//So ... before sending last block - we are switching to blocking mode....
				//this will ensure that stream will finish ....and terminate correctly at the end.....
				//since the block is smaller than internal buffer of the stream - it should return immidiately after accepting the block....
				if ($block_size <= $this->max_block_size)
				{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n||| LAST BLOCK ......... SETTING STREAM TO BLOCKING.......\n";
//---------------------------------------------------------------------------------------------------------------
					stream_set_blocking($this->handle_stream_remote,1);
				}else $block_size=$this->max_block_size; //never ask more than max defined bytes.... (read the class description)

				$bytes_left=$this->chunk_size-$this->transfered_bytes;
				if ($rb=stream_copy_to_stream($this->handle_stream_remote,$this->handle_stream_local,$block_size))
				{
					//block accepted ....keep asking for more....we might get some...
					$block_size-=$rb;
					while ($block_size && ($ogrb=stream_copy_to_stream($this->handle_stream_remote,$this->handle_stream_local,$block_size)))
					{
						$block_size-=$ogrb;
						$rb+=$ogrb;
					};
					$this->CalculateCurrentSpeed();
					$this->last_block_size	=$rb;
					$this->transfered_bytes+=$rb;
					$this->reject_counter		=0;
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG)
{
	$kbs=$this->last_transfer_speed/1024;
	echo "...KICKED..(RB=$rb) (TRANSFERED=$this->transfered_bytes) (LEFT=$bytes_left)  (CHUNK SIZE=$this->chunk_size)...(LBT=$this->last_block_time)..... SPEED=$kbs Kb/sec\n";
};
//---------------------------------------------------------------------------------------------------------------
				}else
				{
					//count rejected block.....
					$this->reject_counter++;
				}
			}else
			{
				//all done....
				//close all streams and check file size to be sure we got everything....
				$this->TerminateStreams();
				if ($this->chunk_size=filesize($this->local_file))
				{
					$this->active=0;
				}else
				{
//---------------------------------------------------------------------------------------------------------------
if (PHP_SSH2MST_DEBUG) echo "\n\n_____________FILE TRANSFER FAILED___________\n";
//---------------------------------------------------------------------------------------------------------------
					$this->parent->transfer_status=PHP_SSH2MST_TRANSFER_STATUS_FAILED;
					$this->active=0;
					$this->failed=1;
				}
				return 0; //indicate that we are done ....
			}
			return 1; //indicate that we are still up and running....
		}
		//not active ...
		return 0;
	}
}
?>