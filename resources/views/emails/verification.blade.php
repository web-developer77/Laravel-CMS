<!DOCTYPE html>
<html>
    <head>
        <title></title>

        <link href="https://fonts.googleapis.com/css?family=Lato:100" rel="stylesheet" type="text/css">
    </head>
    <body>
        <div class="container">
            Hello {{$firstName}}, <br>
    
            It seems like you have signed up at Metroengine. Please <a href="{{$link}}">click here</a> to verify your account and start using system.<br>

            @if(isset($email) && isset($password))
                <p>Your login credentials are as follow :</p>
                <p>Username : {{$email}}</p>
                <p>Password : {{$password}}</p>
            @endif
            <br>

            Thanks,<br>
            Regards. <br>
        </div>
    </body>
</html>
