<?php
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

$xmlseclibs_srcdir = dirname(__FILE__) . '/xmlseclibs-master/src/';
require $xmlseclibs_srcdir . '/XMLSecurityKey.php';
require $xmlseclibs_srcdir . '/XMLSecurityDSig.php';
require $xmlseclibs_srcdir . '/XMLSecEnc.php';
require $xmlseclibs_srcdir . '/Utils/XPath.php';

class Index{
	private $clientId = 'CO0000000033'; //电子口岸备案号
	private $edi = "TEST17";
	private $key = "12345678";
	private $keyFolder = "./key/";//密钥目录
	private $saveSign = true;//是否保存签名数据
	private $postUrl = "http://113.108.156.186:18080/cbt/client/declare/sendMessage.action";//申报地址
	private $ebpCode = "4400000017"; //电商平台代码
	private $ebpName = "测试公司17";//电商平台名称
	private $copCode = "4400000017";//传输企业代码
	private $copName = "测试公司17";//传输企业名称
	private $dxpId = "EXP2016522002580001";//报文传输编号

	//报文申报
	public function sendCEB311Message(){
    	$messageType = "CEB311Message";
    	
    	$gzxml = $this->genarteDoc($messageType);
    	//保存待加签内容
    	if($this->saveSign){
    		file_put_contents('./xml/unsigned-ceb.xml',$gzxml);
    	}
    	//报文加签
    	$signXml = $this->getSignXml($gzxml);
    	$url = $this->postUrl;
    	$data = [
    		'clientid'=>$this->clientId,
    		'key'=>$this->key,
    		'messageType'=>$messageType,
    		'messageText'=>$signXml
    	];
    	//post 申报
		$res = $this->curlPost($url,$data);
		var_dump($res);die;
    }
    //获取海关格式报文
    private function createCEB311Message(){
    	$content = file_get_contents('./xml/CEB311Message.xml');
    	return $content;
    }
    //生成http申报报文
    private function genarteDoc($messageType){
    	$xml = $this->createCEB311Message();
    	$data = base64_encode($xml);
    	$ctime = date('YmdHis');
    	$doc = new \DOMDocument('1.0','utf-8');
    	$doc->formatOutput = true;
    	$guid = $this->getUid($messageType);
    	$root = $doc->createElement('GzeportTransfer');
    	$root->setAttribute('xmlns:ds','http://www.w3.org/2000/09/xmldsig#');
		$root->setAttribute('xmlns:n1','http://www.altova.com/samplexml/other-namespace');
		$root->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
		$doc->appendChild($root);
		$head = [
			'Head'=>[
				'MessageID'=>$guid,
				'MessageType'=>$messageType,
				'Sender'=>$this->edi,
				'Receivers'=>[
					'Receiver'=>'KJPUBLIC'
				],
				'SendTime'=>$ctime,
				'Version'=>'3.0',
				'FileName'=>'待定'
			],
			'Data'=>$data
		];
		$obj = ['obj'=>$head];
		$xml = $this->arrayToXml($obj);
		//echo $xml;die;
		$doc1 = new \DOMDocument();
		$doc1->formatOutput = true;
		$xmlNode = $doc1->loadXML($xml);
		$headNode = $doc1->getElementsByTagName("Head")->item(0);
		$headDom = $doc->importNode($headNode,true);
		$dataNode = $doc1->getElementsByTagName("Data")->item(0);
		$dataDom = $doc->importNode($dataNode,true);
		//var_dump($doc->documentElement);die;
		$doc->documentElement->appendChild($headDom);
		$doc->documentElement->appendChild($dataDom);
		//echo ($doc->saveXML());die;
		return $doc->saveXML();
    }
    //生车messageID
    private function getUid($messageType){
    	$edi = $this->edi;
    	$res = $messageType."_".$edi."_".date('YmdHis').rand(10000,99999);
    	return $res;
    }
    //报文加签
    private function getSignXml($xml){
    	$doc = new \DOMDocument();
    	$doc->formatOutput = true;
    	$doc->loadXML($xml);
    	//var_dump($doc);die;
    	$objDSig = new XMLSecurityDSig("ds");
    	$objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);
    	$objDSig->addReference(
		    $doc,
		    XMLSecurityDSig::SHA1,
		    array('http://www.w3.org/2000/09/xmldsig#enveloped-signature'),
		    array('force_uri' => true)
		);
		$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
		$private_path = $this->keyFolder."privatekey.pem";
		$objKey->loadKey($private_path, TRUE);
		$objDSig->sign($objKey,$doc->documentElement);

		if($this->saveSign){
			$sign_file = "./xml/signed-ceb.xml";
			$doc->save($sign_file);
		}
		
		$xmlHtml = $doc->saveHTML();
		return $xmlHtml;
    }
    private function curlPost($url,$post_data){
        $url = isset($url) ? $url : '';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,20);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		//curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: text/plain'));// 必须声明请求头
        $output = curl_exec($ch);
        if(curl_errno($ch))
		{
		    //echo 'Curl error: ' . curl_error($ch);
		    $code = curl_errno($ch);
		    $msg = curl_error($ch);
		    return ['result'=>false,'errorCode'=>$code,'description'=>$msg];
		}
        curl_close($ch);
        var_dump($output);die;
        return json_decode($output,true);
    }
    private function arrayToXml($arr){
	    $str = "";
	    foreach($arr as $k=>$v){
	        if(is_array($v)){
	            $vkey = key($v);
	            if(is_numeric($vkey)){
	                foreach($v as $key=>$val){
	                    $str .= "<{$k}>";
	                    $str .= $this->arrayToXml($val);
	                    $str .= "</{$k}>";
	                }
	            }else{
	                $tmp = $this->arrayToXml($v);
	                $str .= "<{$k}>{$tmp}</{$k}>";
	            }
	        }else{
	            $str .= "<{$k}>";
	            $str .= $v;
	            $str .= "</{$k}>";
	        }
	    }
	    return $str;
	}

}

$index = new Index();
$res = $index->sendCEB311Message();
