<?php
/**
 * 
 * @authors Your Name (you@example.org)
 * @date    2018-10-26 10:54:10
 * @version $Id$
 */
	header("content-Type: text/html; charset=utf-8");

	require './class.phpmailer.php';
	require './class.smtp.php';
	
	//忽略浏览器状态运行
	ignore_user_abort();
	//执行程序的间隔时间 60秒
	$intervalTime = 60;
	//设置程序等待时间为无限制
	set_time_limit(0);
	//echo $url;
	$status = include './status.php';//返回值为 1 及开始运行，返回值为 0 即停止
	if ($status){
		alertAndSendMail("测试定时任务，每30秒发一份邮件"." status = ".$status." intervalTime = ".$intervalTime." url = ".$url." time = ".date("Y-m-d H:i:s",time()));
		sleep($intervalTime);
		getSelf();
	}else{
		sleep(30);
		getSelf();
	}


	//自身调用
	function getSelf(){
		$url="http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		//初始化
		$curl = curl_init();
		//设置抓取的url
		curl_setopt($curl, CURLOPT_URL, $url);
		//设置头文件的信息作为数据流输出
		curl_setopt($curl, CURLOPT_HEADER, 1);
		//设置获取的信息以文件流的形式返回，而不是直接输出。
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 0);
		//执行命令
		$data = curl_exec($curl);
		//关闭URL请求
		curl_close($curl);
	}

	//读取文件
	function readLogFile(){
		//读取文件
		$logFilePath = "./AutoVueLog.txt";
		$logFile = fopen($logFilePath, "r") or die("Unable to open file!");
		//获取文本内的信息到缓存
		$logInfo = fread($logFile,filesize($logFilePath));
		//清空源文件
		$logFile = fopen($logFilePath, "w") or die("Unable to open file!");
		fwrite($logFile, "");
		fclose($logFile);

		//创建或打开历史记录文件
		$oldLogFilePath = "./old/AutoVueLog.old".time();
		$oldLogFile = fopen($oldLogFilePath, "a") or die ("Unable to open or creat file!");
		//追加前一小时内记录到历史文件
		fwrite($oldLogFile, $logInfo);
		fclose($oldLogFile);

		//拆分为组数
		$logInfoArrayOld = explode(";", $logInfo);
	
		//创建新的空二维数组以便接收后面拆分形成的数组
		$logInfoArrayNew = array();
		//循环，将一条信息拆分为 用户 时间 图纸 组成数组并插入新二维数组
		for ($i=0; $i <count($logInfoArrayOld)-1; $i++) {
			//接收拆分一条信息形成的输出
			$infoArray = splitOneInfo($logInfoArrayOld[$i]);
			//插入到新二维数组
			$logInfoArrayNew[$i] = $infoArray;
		}
		
		//将日志保存到数据库
		$currentCount = count($logInfoArrayNew);
		saveToDataBase($logInfoArrayNew,$currentCount);
	}

	//拆分一条信息并重新组合为数组后返回
	function splitOneInfo($temp){
		$oneInfoArray = array("","","");
		$tempArray = explode("-",$temp);
		if(!strcasecmp(trim($tempArray[0]),trim("cat"))){
			$i = count($tempArray);
			//cat用户
			$oneInfoArray[0] = ltrim($tempArray[0]."-".$tempArray[1]);
			//cat时间
			$oneInfoArray[1] = $tempArray[2]."-".$tempArray[3]."-".$tempArray[4];
			//cat文件
			$oneInfoArray[2] = $tempArray[5];
			for($t=6; $t < $i; $t++){
				$oneInfoArray[2] = $oneInfoArray[2].'-'.$tempArray[$t];
			}
		}else{
			$i = count($tempArray);
			//用户
			$oneInfoArray[0] = ltrim($tempArray[0]);
			//时间
			$oneInfoArray[1] = $tempArray[1]."-".$tempArray[2]."-".$tempArray[3];
			//文件名
			$oneInfoArray[2] = $tempArray[4];
			for($t=5,$value; $t < $i; $t++){
				$oneInfoArray[2] = $oneInfoArray[2].'-'.$tempArray[$t];
			}
		}
		return $oneInfoArray;
	}


	//将文件信息保存到数据库
	function saveToDataBase($logInfoArray,$count){

		//数据库信息
		$serverName = "127.0.0.1,1433";
		$account = "sa";
		$password = "sqlserver1997@";
		$dataBase = "AutoVueLog";
		$table = "records";
	
		$conn = mssql_connect($serverName,$account,$password);
	
		if ($conn) {
			# code...
			mssql_select_db($dataBase,$conn);
			echo "connection established!<br />";

			//声明影响行数
			$affectedRows = 0;

			for ($i=0; $i < $count; $i++) { 
				# code...
				$tempLogInfo = $logInfoArray[$i];

				//将一条信息拼凑为SQL语句
				$query = "insert into [".$dataBase."].[dbo].[".$table
						."](account,dateTime,fileName) values ('".$tempLogInfo[0]
						."','".$tempLogInfo[1]."','".$tempLogInfo[2]."');";
				// echo $query."<br />";
				
				//执行SQL语句
				$result = mssql_query($query);
				if ($result) {
					# code...
					$affectedRows = $affectedRows+mssql_rows_affected($conn);
				}else{
					echo "执行插入第".$i."条数据时出错！<br />错误信息：".mssql_get_last_message();
				}

				if ($affectedRows == $count) {
					echo "数据插入成功！<br />";

					//======执行触发报警=========
					//用户类别
					$result = mssql_query("select DISTINCT account FROM [".$dataBase."].[dbo].[".$table
						."];");
					$accountArray = array();
					while ($tempArray = mssql_fetch_array($result)) {
						# code...
						$accountArray[] = $tempArray[0];
					}
					//$accountNum = count($accountArray);
					foreach ($accountArray as $value) {
						# code...
						switch ($value) {
							case 'cat-wr':isOverDoes(15,$value);
								break;
							case 'hxg':isOverDoes(15,$value);
								break;
							case 'qa1':isOverDoes(15,$value);
								break;
							case 'qa2':isOverDoes(15,$value);
								break;
							case 'Test Everything':isOverDoes(20,$value);
								break;
							case 'tuku':isOverDoes(20,$value);
								break;
							case 'vip':isOverDoes(5,$value);
								break;
							default:isOverDoes(10,$value);
								break;
						}
					}
				}
			}

		}else{
			echo "connection filed!";
		}

		if (mssql_close($conn)) {
			# code...
			echo "DataBase Closed!";
		}else{
			echo "Close DataBase Failed!";
		}
	}

	//触发报警
	function isOverDoes($canReadNum,$account){
		//当前时间
		$now = date('Y-m-d H:i:s', time());
		//前一小时
		//$mtime= date("Y-m-d H:i:s", strtotime("-1 hour"));
		$mtime = date("Y-m-d H:i:s","2018-10-12 08:47:41");
		$query = "select count(*) as readNum from [AutoVueLog].[dbo].[records] where account = '".$account."' and (datetime >= '".$mtime."' and datetime <= '".$now."')";
		$result = mssql_query($query);
		$readNum = mssql_fetch_array($result);
		//echo $query."<br />";
		
		//1小时阅读适量超过限制，激活警报发送邮件
		if ($readNum[0] > $canReadNum) {
			# code...
			$alertMassage = "用户：".$account." 在最近一小时内浏览图纸或文档数量为：".$readNum[0]." 份，超过预期数值！请确认！";
			echo $alertMassage."<br />";
			alertAndSendMail($alertMassage);
		}
	}

	//发送邮件
	function alertAndSendMail($alertMassage){
		$mail = new PHPMailer();

		$mail->SMTPDebug = 0;
		$mail->isSMTP();
		//smtp需要鉴权 这个必须是true
		$mail->SMTPAuth=true;
		$mail->Host = 'smtp.hi-lex.com.cn';

		//设置使用ssl加密方式登录鉴权
		$mail->SMTPSecure = 'ssl';
		
		//设置ssl连接smtp服务器的远程服务器端口号 可选465或587
		$mail->Port = 465;
		$mail->Hostname = 'localhost';
		$mail->CharSet = 'UTF-8';
		$mail->FromName = 'MailMaster';
		$mail->Username ='shihongxin@hi-lex.com.cn';
		$mail->Password = 'hilex1997@';
		$mail->From = 'shihongxin@hi-lex.com.cn';
		$mail->addAddress('shihongxin@hi-lex.com.cn','石洪鑫');
		$mail->addAddress('shihongxins@163.com','石洪鑫');
		$mail->Subject = '使用 AutoVue 浏览图纸或文档过量警报！';
		$mail->Body = $alertMassage;

		$result = $mail->send();
		if($result){
			echo '发送邮件成功！  '.date('Y-m-d H:i:s');
		}else{
			echo '发送邮件失败，错误信息未：'.$mail->ErrorInfo;
		}
	}

?>