<?php 
namespace ImapReader;

class File {
	private $type;
	private $content;
	private $name;
	
	public function __construct($type, $name, $content) {
		$this->type = $type;
		$this->content = $content;
		$this->name = $name;
	}
	
	public function getType() {
		return $this->type;
	}
	
	public function getContent() {
		return $this->content;
	}
	
	public function getName() {
		return $this->name;
	}
}

class Email {
	private $to;
	private $from;
	private $subject;
	private $date;
	private $body;
	private $attachments = array();
	private $messageId;
	private $bodyType;
	
	public function __construct($to, $from, $subject, \DateTime2 $date, $body, $messageId, $bodyType = 'text/html') {
		$this->to = $to;
		$this->from = $from;
		$this->subject = $subject;
		$this->date = $date;
		
		$this->body = $body;
		$this->messageId = $messageId;
		
		$this->bodyType = $bodyType;
	}
	
	public function getMessageId() {
		return $this->messageId;
	}
	
	public function getBody() {
		return $this->body;
	}
	
	public function getFrom() {
		return $this->from;
	}
	
	public function getTo() {
		return $this->to;
	}
	
	public function getSubject() {
		return $this->subject;
	}
	
	public function getDate() {
		return $this->date;
	}
	
	public function getBodyType() {
		return $this->bodyType;
	}
	public function getAttachments() {
		return $this->attachments;
	}
	
	public function addAttachment($id, File $file) {
		if ($id == '') $id = uniqid();
		$this->attachments[$id] = $file;
	}
}

class EmailPart {
	public $partNo;
	public $mimeType;
	public $baseType;
	public $body;
	public $id;
	public $parameters = array();
}

class EmailAccount {
	private $username;
	private $password;
	private $server;
	private $port;
	private $connStr;
	
	private $connection;
	private $options = array('');
	 
	private $messages = array();
	
	public function __construct($username, $password, $server = 'localhost', array  $options = array(), $port = 143) {
		$this->username = $username;
		$this->password = $password;
		$this->server = $server;
		$this->port = $port;
		foreach($options as $opt) $this->options[] = $opt;
	}
	

	public function connect() {
		$this->connStr = '{' . $this->server . ':' . $this->port . implode('/', $this->options) . '}';
		$this->connection = imap_open($this->connStr . 'INBOX', $this->username, $this->password);
		if (!$this->connection) throw new \Exception('Could not connect to imap server');		
	}
	
	public function numMessages() {
		if (!$this->connection) $this->connect();
		if ($results = imap_search($this->connection, 'ALL')) return $results;
		else return array();
	}
	
	public function moveEmailTo(Email $email, $destination) {
		$mailboxes = imap_getmailboxes($this->connection, $this->connStr, '*');
		$found = false;
		foreach ($mailboxes as $mailbox) {
			if ($mailbox->name == $this->connStr . 'INBOX.' . $destination) $found = true;
		}
		
		if (!$found) imap_createmailbox($this->connection, $this->connStr . 'INBOX.' . $destination);
		
		return imap_mail_move($this->connection, $email->getMessageId(), 'INBOX.' . $destination);
	}
	
	
	private $partTypes = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
	
	public function buildparts ($struct, $messageNo, $partNo = null) {
		$arr = [];
		if ($struct->type == 1) {

			$count = 1;
			foreach ($struct->parts as $part) $arr = array_merge($arr, $this->buildParts($part, $messageNo, ( ($partNo != null) ?  $partNo . '.' : '' ) . $count++));
			
			return $arr; 
		}
		else if ($struct->type == 2) {
			return array_merge($arr, $this->buildParts($struct->parts[0], $messageNo, $partNo));
		}
		else {
			$cls = new EmailPart;
			$cls->partNo = $partNo;
			$cls->baseType = $this->partTypes[$struct->type];
			$cls->mimeType = $this->partTypes[$struct->type] . '/' . strtolower($struct->subtype);
			if (isset($struct->id)) $cls->id = rtrim(ltrim($struct->id, '<'), '>');
			$cls->parameters = $struct->parameters;
			
			#$cls->header = imap_fetchheader($this->connection, $messageNo, $partNo);
			
			if ($cls->mimeType == 'text/html' || $cls->mimeType == 'text/plain') {
				if ($struct->encoding == '3') {
					$cls->body = utf8_decode(base64_decode(imap_fetchbody($this->connection, $messageNo, $partNo)));
				}
				else $cls->body = utf8_decode(quoted_printable_decode(imap_fetchbody($this->connection, $messageNo, $partNo)));
			}
			else $cls->body = imap_fetchbody($this->connection, $messageNo, $partNo);
			
			$arr[] = $cls;
			return $arr;
		}


	}
	
	public function delete(Email $email) {
		//imap_delete($this->connection, );
	}
	
	public function markRead(Email $email) {
	##	echo 'mar';
	##	echo imap_setflag_full($this->connection, $email->getMessageId(), '\Seen');
	}
	
	private function findPartByType(array $parts, $type) {
		foreach ($parts as $part) {
			if ($part->mimeType == $type) {
				return $part;
			}
		}
		return false;
	}
	
	private function fetch() {

		foreach ($this->numMessages() as $i) {	
			
			$struct = imap_fetchstructure($this->connection, $i);
			
			//print_r($struct);
		
			$pts = $this->buildParts($struct, $i);
			$body = null;
			
			$bodyType = 'text/html';
			$body = $this->findPartByType($pts, 'text/html');
						
			if (!$body) {
				$body = $this->findPartByType($pts, 'text/plain');
				$bodyType = 'text/plain';
			}
			
			
			$body = $body->body;
			
			$header = imap_headerinfo($this->connection, $i);

			if ($header->Unseen == 'U' || trim($header->Unseen) == '') {
				$email = new Email($header->toaddress, $header->from[0]->mailbox . '@' . $header->from[0]->host, $header->subject, new \DateTime2($header->date),  $body, $i, $bodyType);
				
				foreach ($pts as $part) {
					if ($part->baseType != 'text' && $part->baseType != 'multipart') {
						$name = '';
						//print_r($part->parameters);
						foreach ($part->parameters as $param) {
							if ($param->attribute == 'name') $name = $param->value;
						}
						$file = new File($part->mimeType, $name, base64_decode($part->body));
						$email->addAttachment($part->id, $file);
					}
				}
				
				$this->messages[] = $email;
			}
			
		}
		
	}
	
	public function getMessages($refresh = false) {
		if (empty($this->messages) || $refresh) $this->fetch();
		return $this->messages;		
	}
}
