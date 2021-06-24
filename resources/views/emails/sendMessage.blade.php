<!DOCTYPE html>
<html>
<head>
	<title></title>
</head>
<body>
	<p>Hello {{$receiver->firstName}},</p>
	<p> You have one new message from {{ $sender->firstName }} {{ $sender->lastName }}. </p>
	<p> Message : {{ $msg }} </p>
	
	<br><br>
	Thanks,<br>
	Regards. <br>
</body>
</html>