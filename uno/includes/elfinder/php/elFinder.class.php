<?php
 class elFinder{ protected $version='2.0'; protected $volumes=array(); public static $netDrivers=array(); public static $volumesCnt=1; protected $default=null; protected $commands=array('open'=>array('target'=>false,'tree'=>false,'init'=>false,'mimes'=>false),'ls'=>array('target'=>true,'mimes'=>false),'tree'=>array('target'=>true),'parents'=>array('target'=>true),'tmb'=>array('targets'=>true),'file'=>array('target'=>true,'download'=>false),'size'=>array('targets'=>true),'mkdir'=>array('target'=>true,'name'=>true),'mkfile'=>array('target'=>true,'name'=>true,'mimes'=>false),'rm'=>array('targets'=>true),'rename'=>array('target'=>true,'name'=>true,'mimes'=>false),'duplicate'=>array('targets'=>true,'suffix'=>false),'paste'=>array('dst'=>true,'targets'=>true,'cut'=>false,'mimes'=>false),'upload'=>array('target'=>true,'FILES'=>true,'mimes'=>false,'html'=>false),'get'=>array('target'=>true),'put'=>array('target'=>true,'content'=>'','mimes'=>false),'archive'=>array('targets'=>true,'type'=>true,'mimes'=>false),'extract'=>array('target'=>true,'mimes'=>false),'search'=>array('q'=>true,'mimes'=>false),'info'=>array('targets'=>true),'dim'=>array('target'=>true),'resize'=>array('target'=>true,'width'=>true,'height'=>true,'mode'=>false,'x'=>false,'y'=>false,'degree'=>false),'netmount'=>array('protocol'=>true,'host'=>true,'path'=>false,'port'=>false,'user'=>true,'pass'=>true,'alias'=>false,'options'=>false)); protected $listeners=array(); protected $time=0; protected $loaded=false; protected $debug=false; protected $timeout=0; protected $uploadDebug=''; public $mountErrors=array();const ERROR_UNKNOWN='errUnknown';const ERROR_UNKNOWN_CMD='errUnknownCmd';const ERROR_CONF='errConf';const ERROR_CONF_NO_JSON='errJSON';const ERROR_CONF_NO_VOL='errNoVolumes';const ERROR_INV_PARAMS='errCmdParams';const ERROR_OPEN='errOpen';const ERROR_DIR_NOT_FOUND='errFolderNotFound';const ERROR_FILE_NOT_FOUND='errFileNotFound';const ERROR_TRGDIR_NOT_FOUND='errTrgFolderNotFound';const ERROR_NOT_DIR='errNotFolder';const ERROR_NOT_FILE='errNotFile';const ERROR_PERM_DENIED='errPerm';const ERROR_LOCKED='errLocked';const ERROR_EXISTS='errExists';const ERROR_INVALID_NAME='errInvName';const ERROR_MKDIR='errMkdir';const ERROR_MKFILE='errMkfile';const ERROR_RENAME='errRename';const ERROR_COPY='errCopy';const ERROR_MOVE='errMove';const ERROR_COPY_FROM='errCopyFrom';const ERROR_COPY_TO='errCopyTo';const ERROR_COPY_ITSELF='errCopyInItself';const ERROR_REPLACE='errReplace';const ERROR_RM='errRm';const ERROR_RM_SRC='errRmSrc';const ERROR_UPLOAD='errUpload';const ERROR_UPLOAD_FILE='errUploadFile';const ERROR_UPLOAD_NO_FILES='errUploadNoFiles';const ERROR_UPLOAD_TOTAL_SIZE='errUploadTotalSize';const ERROR_UPLOAD_FILE_SIZE='errUploadFileSize';const ERROR_UPLOAD_FILE_MIME='errUploadMime';const ERROR_UPLOAD_TRANSFER='errUploadTransfer';const ERROR_NOT_REPLACE='errNotReplace';const ERROR_SAVE='errSave';const ERROR_EXTRACT='errExtract';const ERROR_ARCHIVE='errArchive';const ERROR_NOT_ARCHIVE='errNoArchive';const ERROR_ARCHIVE_TYPE='errArcType';const ERROR_ARC_SYMLINKS='errArcSymlinks';const ERROR_ARC_MAXSIZE='errArcMaxSize';const ERROR_RESIZE='errResize';const ERROR_UNSUPPORT_TYPE='errUsupportType';const ERROR_NOT_UTF8_CONTENT='errNotUTF8Content';const ERROR_NETMOUNT='errNetMount';const ERROR_NETMOUNT_NO_DRIVER='errNetMountNoDriver';const ERROR_NETMOUNT_FAILED='errNetMountFailed';const ERROR_SESSION_EXPIRES='errSessionExpires';const ERROR_CREATING_TEMP_DIR='errCreatingTempDir';const ERROR_FTP_DOWNLOAD_FILE='errFtpDownloadFile';const ERROR_FTP_UPLOAD_FILE='errFtpUploadFile';const ERROR_FTP_MKDIR='errFtpMkdir';const ERROR_ARCHIVE_EXEC='errArchiveExec';const ERROR_EXTRACT_EXEC='errExtractExec'; public function __construct($opts){if(session_id()==''){session_start();}$this->time=$this->utime();$this->debug=(isset($opts['debug'])&&$opts['debug']?true:false);$this->timeout=(isset($opts['timeout'])?$opts['timeout']:0);setlocale(LC_ALL,!empty($opts['locale'])?$opts['locale']:'en_US.UTF-8');if(!empty($opts['bind'])&&is_array($opts['bind'])){foreach($opts['bind'] as $cmd=>$handler){$this->bind($cmd,$handler);}}if(!isset($opts['roots'])||!is_array($opts['roots'])){$opts['roots']=array();}foreach($this->getNetVolumes() as $root){$opts['roots'][]=$root;}foreach($opts['roots'] as $i=>$o){$class='elFinderVolume'.(isset($o['driver'])?$o['driver']:'');if(class_exists($class)){$volume=new $class();try{if($volume->mount($o)){$id=$volume->id();$this->volumes[$id]=$volume;if(!$this->default&&$volume->isReadable()){$this->default=$this->volumes[$id];}}else {$this->mountErrors[]='Driver "'.$class.'" : '.implode(' ',$volume->error());}}catch(Exception$e){$this->mountErrors[]='Driver "'.$class.'" : '.$e->getMessage();}}else {$this->mountErrors[]='Driver "'.$class.'" does not exists';}}$this->loaded=!empty($this->default);} public function loaded(){return $this->loaded;} public function version(){return $this->version;} public function bind($cmd,$handler){$cmds=$cmd=='*'?array_keys($this->commands):array_map('trim',explode(' ',$cmd));foreach($cmds as $cmd){if($cmd){if(!isset($this->listeners[$cmd])){$this->listeners[$cmd]=array();}if(is_callable($handler)){$this->listeners[$cmd][]=$handler;}}}return $this;} public function unbind($cmd,$handler){if(!empty($this->listeners[$cmd])){foreach($this->listeners[$cmd] as $i=>$h){if($h===$handler){unset($this->listeners[$cmd][$i]);return $this;}}}return $this;} public function commandExists($cmd){return $this->loaded&&isset($this->commands[$cmd])&&method_exists($this,$cmd);} public function getVolume($hash){return $this->volume($hash);} public function commandArgsList($cmd){return $this->commandExists($cmd)?$this->commands[$cmd]:array();} private function session_expires(){if(!isset($_SESSION['LAST_ACTIVITY'])){$_SESSION['LAST_ACTIVITY']=time();return false;}if(($this->timeout>0)&&(time()-$_SESSION['LAST_ACTIVITY']>$this->timeout)){return true;}$_SESSION['LAST_ACTIVITY']=time();return false;} public function exec($cmd,$args){if(!$this->loaded){return array('error'=>$this->error(self::ERROR_CONF,self::ERROR_CONF_NO_VOL));}if($this->session_expires()){return array('error'=>$this->error(self::ERROR_SESSION_EXPIRES));}if(!$this->commandExists($cmd)){return array('error'=>$this->error(self::ERROR_UNKNOWN_CMD));}if(!empty($args['mimes'])&&is_array($args['mimes'])){foreach($this->volumes as $id=>$v){$this->volumes[$id]->setMimesFilter($args['mimes']);}}$result=$this->$cmd($args);if(isset($result['removed'])){foreach($this->volumes as $volume){$result['removed']=array_merge($result['removed'],$volume->removed());$volume->resetRemoved();}}if(!empty($this->listeners[$cmd])){foreach($this->listeners[$cmd] as $handler){if(call_user_func_array($handler,array($cmd,&$result,$args,$this))){$result['sync']=true;}}}if(!empty($result['removed'])){$removed=array();foreach($result['removed'] as $file){$removed[]=$file['hash'];}$result['removed']=array_unique($removed);}if(!empty($result['added'])){$result['added']=$this->filter($result['added']);}if(!empty($result['changed'])){$result['changed']=$this->filter($result['changed']);}if($this->debug||!empty($args['debug'])){$result['debug']=array('connector'=>'php','phpver'=>PHP_VERSION,'time'=>$this->utime()-$this->time,'memory'=>(function_exists('memory_get_peak_usage')?ceil(memory_get_peak_usage()/1024).'Kb / ':'').ceil(memory_get_usage()/1024).'Kb / '.ini_get('memory_limit'),'upload'=>$this->uploadDebug,'volumes'=>array(),'mountErrors'=>$this->mountErrors);foreach($this->volumes as $id=>$volume){$result['debug']['volumes'][]=$volume->debug();}}foreach($this->volumes as $volume){$volume->umount();}return $result;} public function realpath($hash){if(($volume=$this->volume($hash))==false){return false;}return $volume->realpath($hash);} protected function getNetVolumes(){return isset($_SESSION['elFinderNetVolumes'])&&is_array($_SESSION['elFinderNetVolumes'])?$_SESSION['elFinderNetVolumes']:array();} protected function saveNetVolumes($volumes){$_SESSION['elFinderNetVolumes']=$volumes;} public function error(){$errors=array();foreach(func_get_args() as $msg){if(is_array($msg)){$errors=array_merge($errors,$msg);}else {$errors[]=$msg;}}return count($errors)?$errors:array(self::ERROR_UNKNOWN);} protected function netmount($args){$options=array();$protocol=$args['protocol'];$driver=isset(self::$netDrivers[$protocol])?$protocol:'';$class='elfindervolume'.$protocol;if(!$driver){return array('error'=>$this->error(self::ERROR_NETMOUNT,$args['host'],self::ERROR_NETMOUNT_NO_DRIVER));}if(!$args['path']){$args['path']='/';}foreach($args as $k=>$v){if($k!='options'&&$k!='protocol'&&$v){$options[$k]=$v;}}if(is_array($args['options'])){foreach($args['options'] as $key=>$value){$options[$key]=$value;}}$volume=new $class();if($volume->mount($options)){$netVolumes=$this->getNetVolumes();$options['driver']=$driver;$netVolumes[]=$options;$netVolumes=array_unique($netVolumes);$this->saveNetVolumes($netVolumes);return array('sync'=>true);}else {return array('error'=>$this->error(self::ERROR_NETMOUNT,$args['host'],implode(' ',$volume->error())));}} protected function open($args){$target=$args['target'];$init=!empty($args['init']);$tree=!empty($args['tree']);$volume=$this->volume($target);$cwd=$volume?$volume->dir($target,true):false;$hash=$init?'default folder':'#'.$target;if((!$cwd||!$cwd['read'])&&$init){$volume=$this->default;$cwd=$volume->dir($volume->defaultPath(),true);}if(!$cwd){return array('error'=>$this->error(self::ERROR_OPEN,$hash,self::ERROR_DIR_NOT_FOUND));}if(!$cwd['read']){return array('error'=>$this->error(self::ERROR_OPEN,$hash,self::ERROR_PERM_DENIED));}$files=array();if($args['tree']){foreach($this->volumes as $id=>$v){if(($tree=$v->tree('',0,$cwd['hash']))!=false){$files=array_merge($files,$tree);}}}if(($ls=$volume->scandir($cwd['hash']))===false){return array('error'=>$this->error(self::ERROR_OPEN,$cwd['name'],$volume->error()));}foreach($ls as $file){if(!in_array($file,$files)){$files[]=$file;}}$result=array('cwd'=>$cwd,'options'=>$volume->options($cwd['hash']),'files'=>$files);if(!empty($args['init'])){$result['api']=$this->version;$result['uplMaxSize']=ini_get('upload_max_filesize');$result['netDrivers']=array_keys(self::$netDrivers);}return $result;} protected function ls($args){$target=$args['target'];if(($volume=$this->volume($target))==false||($list=$volume->ls($target))===false){return array('error'=>$this->error(self::ERROR_OPEN,'#'.$target));}return array('list'=>$list);} protected function tree($args){$target=$args['target'];if(($volume=$this->volume($target))==false||($tree=$volume->tree($target))==false){return array('error'=>$this->error(self::ERROR_OPEN,'#'.$target));}return array('tree'=>$tree);} protected function parents($args){$target=$args['target'];if(($volume=$this->volume($target))==false||($tree=$volume->parents($target))==false){return array('error'=>$this->error(self::ERROR_OPEN,'#'.$target));}return array('tree'=>$tree);} protected function tmb($args){$result=array('images'=>array());$targets=$args['targets'];foreach($targets as $target){if(($volume=$this->volume($target))!=false&&(($tmb=$volume->tmb($target))!=false)){$result['images'][$target]=$tmb;}}return $result;} protected function file($args){$target=$args['target'];$download=!empty($args['download']);$h403='HTTP/1.x 403 Access Denied';$h404='HTTP/1.x 404 Not Found';if(($volume=$this->volume($target))==false){return array('error'=>'File not found','header'=>$h404,'raw'=>true);}if(($file=$volume->file($target))==false){return array('error'=>'File not found','header'=>$h404,'raw'=>true);}if(!$file['read']){return array('error'=>'Access denied','header'=>$h403,'raw'=>true);}if(($fp=$volume->open($target))==false){return array('error'=>'File not found','header'=>$h404,'raw'=>true);}if($download){$disp='attachment';$mime='application/octet-stream';}else {$disp=preg_match('/^(image|text)/i',$file['mime'])||$file['mime']=='application/x-shockwave-flash'?'inline':'attachment';$mime=$file['mime'];}$filenameEncoded=rawurlencode($file['name']);if(strpos($filenameEncoded,'%')===false){$filename='filename="'.$file['name'].'"';}else {$ua=$_SERVER["HTTP_USER_AGENT"];if(preg_match('/MSIE [4-8]/',$ua)){$filename='filename="'.$filenameEncoded.'"';}elseif(strpos($ua,'Chrome')===false&&strpos($ua,'Safari')!==false){$filename='filename="'.str_replace('"','',$file['name']).'"';}else {$filename='filename*=UTF-8\'\''.$filenameEncoded;}}$result=array('volume'=>$volume,'pointer'=>$fp,'info'=>$file,'header'=>array('Content-Type: '.$mime,'Content-Disposition: '.$disp.'; '.$filename,'Content-Location: '.$file['name'],'Content-Transfer-Encoding: binary','Content-Length: '.$file['size'],'Connection: close'));return $result;} protected function size($args){$size=0;foreach($args['targets'] as $target){if(($volume=$this->volume($target))==false||($file=$volume->file($target))==false||!$file['read']){return array('error'=>$this->error(self::ERROR_OPEN,'#'.$target));}$size+=$volume->size($target);}return array('size'=>$size);} protected function mkdir($args){$target=$args['target'];$name=$args['name'];if(($volume=$this->volume($target))==false){return array('error'=>$this->error(self::ERROR_MKDIR,$name,self::ERROR_TRGDIR_NOT_FOUND,'#'.$target));}return ($dir=$volume->mkdir($target,$name))==false?array('error'=>$this->error(self::ERROR_MKDIR,$name,$volume->error())):array('added'=>array($dir));} protected function mkfile($args){$target=$args['target'];$name=$args['name'];if(($volume=$this->volume($target))==false){return array('error'=>$this->error(self::ERROR_MKFILE,$name,self::ERROR_TRGDIR_NOT_FOUND,'#'.$target));}return ($file=$volume->mkfile($target,$args['name']))==false?array('error'=>$this->error(self::ERROR_MKFILE,$name,$volume->error())):array('added'=>array($file));} protected function rename($args){$target=$args['target'];$name=$args['name'];if(($volume=$this->volume($target))==false||($rm=$volume->file($target))==false){return array('error'=>$this->error(self::ERROR_RENAME,'#'.$target,self::ERROR_FILE_NOT_FOUND));}$rm['realpath']=$volume->realpath($target);return ($file=$volume->rename($target,$name))==false?array('error'=>$this->error(self::ERROR_RENAME,$rm['name'],$volume->error())):array('added'=>array($file),'removed'=>array($rm));} protected function duplicate($args){$targets=is_array($args['targets'])?$args['targets']:array();$result=array('added'=>array());$suffix=empty($args['suffix'])?'copy':$args['suffix'];foreach($targets as $target){if(($volume=$this->volume($target))==false||($src=$volume->file($target))==false){$result['warning']=$this->error(self::ERROR_COPY,'#'.$target,self::ERROR_FILE_NOT_FOUND);break;}if(($file=$volume->duplicate($target,$suffix))==false){$result['warning']=$this->error($volume->error());break;}$result['added'][]=$file;}return $result;} protected function rm($args){$targets=is_array($args['targets'])?$args['targets']:array();$result=array('removed'=>array());foreach($targets as $target){if(($volume=$this->volume($target))==false){$result['warning']=$this->error(self::ERROR_RM,'#'.$target,self::ERROR_FILE_NOT_FOUND);return $result;}if(!$volume->rm($target)){$result['warning']=$this->error($volume->error());return $result;}}return $result;} protected function upload($args){$target=$args['target'];$volume=$this->volume($target);$files=isset($args['FILES']['upload'])&&is_array($args['FILES']['upload'])?$args['FILES']['upload']:array();$header=empty($args['html'])?array():array('header'=>'Content-Type: text/html; charset=utf-8');$result=array_merge(array('added'=>array()),$header);if(empty($files)){return array_merge(array('error'=>$this->error(self::ERROR_UPLOAD,self::ERROR_UPLOAD_NO_FILES)),$header);}if(!$volume){return array_merge(array('error'=>$this->error(self::ERROR_UPLOAD,self::ERROR_TRGDIR_NOT_FOUND,'#'.$target)),$header);}foreach($files['name'] as $i=>$name){if(($error=$files['error'][$i])>0){$result['warning']=$this->error(self::ERROR_UPLOAD_FILE,$name,$error==UPLOAD_ERR_INI_SIZE||$error==UPLOAD_ERR_FORM_SIZE?self::ERROR_UPLOAD_FILE_SIZE:self::ERROR_UPLOAD_TRANSFER);$this->uploadDebug='Upload error code: '.$error;break;}$tmpname=$files['tmp_name'][$i];if(($fp=fopen($tmpname,'rb'))==false){$result['warning']=$this->error(self::ERROR_UPLOAD_FILE,$name,self::ERROR_UPLOAD_TRANSFER);$this->uploadDebug='Upload error: unable open tmp file';break;}if(($file=$volume->upload($fp,$target,$name,$tmpname))===false){$result['warning']=$this->error(self::ERROR_UPLOAD_FILE,$name,$volume->error());fclose($fp);break;}fclose($fp);$result['added'][]=$file;}return $result;} protected function paste($args){$dst=$args['dst'];$targets=is_array($args['targets'])?$args['targets']:array();$cut=!empty($args['cut']);$error=$cut?self::ERROR_MOVE:self::ERROR_COPY;$result=array('added'=>array(),'removed'=>array());if(($dstVolume=$this->volume($dst))==false){return array('error'=>$this->error($error,'#'.$targets[0],self::ERROR_TRGDIR_NOT_FOUND,'#'.$dst));}foreach($targets as $target){if(($srcVolume=$this->volume($target))==false){$result['warning']=$this->error($error,'#'.$target,self::ERROR_FILE_NOT_FOUND);break;}if(($file=$dstVolume->paste($srcVolume,$target,$dst,$cut))==false){$result['warning']=$this->error($dstVolume->error());break;}$result['added'][]=$file;}return $result;} protected function get($args){$target=$args['target'];$volume=$this->volume($target);if(!$volume||($file=$volume->file($target))==false){return array('error'=>$this->error(self::ERROR_OPEN,'#'.$target,self::ERROR_FILE_NOT_FOUND));}if(($content=$volume->getContents($target))===false){return array('error'=>$this->error(self::ERROR_OPEN,$volume->path($target),$volume->error()));}$json=json_encode($content);if($json=='null'&&strlen($json)<strlen($content)){return array('error'=>$this->error(self::ERROR_NOT_UTF8_CONTENT,$volume->path($target)));}return array('content'=>$content);} protected function put($args){$target=$args['target'];if(($volume=$this->volume($target))==false||($file=$volume->file($target))==false){return array('error'=>$this->error(self::ERROR_SAVE,'#'.$target,self::ERROR_FILE_NOT_FOUND));}if(($file=$volume->putContents($target,$args['content']))==false){return array('error'=>$this->error(self::ERROR_SAVE,$volume->path($target),$volume->error()));}return array('changed'=>array($file));} protected function extract($args){$target=$args['target'];$mimes=!empty($args['mimes'])&&is_array($args['mimes'])?$args['mimes']:array();$error=array(self::ERROR_EXTRACT,'#'.$target);if(($volume=$this->volume($target))==false||($file=$volume->file($target))==false){return array('error'=>$this->error(self::ERROR_EXTRACT,'#'.$target,self::ERROR_FILE_NOT_FOUND));}return ($file=$volume->extract($target))?array('added'=>array($file)):array('error'=>$this->error(self::ERROR_EXTRACT,$volume->path($target),$volume->error()));} protected function archive($args){$type=$args['type'];$targets=isset($args['targets'])&&is_array($args['targets'])?$args['targets']:array();if(($volume=$this->volume($targets[0]))==false){return $this->error(self::ERROR_ARCHIVE,self::ERROR_TRGDIR_NOT_FOUND);}return ($file=$volume->archive($targets,$args['type']))?array('added'=>array($file)):array('error'=>$this->error(self::ERROR_ARCHIVE,$volume->error()));} protected function search($args){$q=trim($args['q']);$mimes=!empty($args['mimes'])&&is_array($args['mimes'])?$args['mimes']:array();$result=array();foreach($this->volumes as $volume){$result=array_merge($result,$volume->search($q,$mimes));}return array('files'=>$result);} protected function info($args){$files=array();foreach($args['targets'] as $hash){if(($volume=$this->volume($hash))!=false&&($info=$volume->file($hash))!=false){$files[]=$info;}}return array('files'=>$files);} protected function dim($args){$target=$args['target'];if(($volume=$this->volume($target))!=false){$dim=$volume->dimensions($target);return $dim?array('dim'=>$dim):array();}return array();} protected function resize($args){$target=$args['target'];$width=$args['width'];$height=$args['height'];$x=(int)$args['x'];$y=(int)$args['y'];$mode=$args['mode'];$bg=null;$degree=(int)$args['degree'];if(($volume=$this->volume($target))==false||($file=$volume->file($target))==false){return array('error'=>$this->error(self::ERROR_RESIZE,'#'.$target,self::ERROR_FILE_NOT_FOUND));}return ($file=$volume->resize($target,$width,$height,$x,$y,$mode,$bg,$degree))?array('changed'=>array($file)):array('error'=>$this->error(self::ERROR_RESIZE,$volume->path($target),$volume->error()));} protected function volume($hash){foreach($this->volumes as $id=>$v){if(strpos(''.$hash,$id)===0){return $this->volumes[$id];}}return false;} protected function toArray($data){return isset($data['hash'])||!is_array($data)?array($data):$data;} protected function hashes($files){$ret=array();foreach($files as $file){$ret[]=$file['hash'];}return $ret;} protected function filter($files){foreach($files as $i=>$file){if(!empty($file['hidden'])||!$this->default->mimeAccepted($file['mime'])){unset($files[$i]);}}return array_merge($files,array());} protected function utime(){$time=explode(" ",microtime());return (double)$time[1]+(double)$time[0];}}
