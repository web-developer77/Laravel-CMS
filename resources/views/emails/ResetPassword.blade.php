<!DOCTYPE html>
<html>
<head>
	<title></title>
</head>
<body>
	<p>Hello {{$user->firstName}},</p>
	<p>It seems like you have requested for password change. Please use verification code : {{$otp}} to regenerate the password. It is valid for 15 minutes. Please do not share your code or password with anyone to avoid misuse of your account.</p>
	<p>If you have not requested for password change, please avoid this email.</p>

	<br><br>
	Thanks,<br>
	Regards. <br>
</body>
</html>