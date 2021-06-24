<!DOCTYPE html>
<html>
<head>
	<title></title>
</head>
<body>
	<p>Hello {{$agent->user->firstName}},</p>
	<p> {{$user->firstName}} has requested to change the status of UnitNo:  {{ $unit->unitNo}} to  <b> {{ $status }} </b>. </p>
	
	<br><br>
	Thanks,<br>
	Regards. <br>
</body>
</html>