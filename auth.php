<?php
require_once 'config.php';
header('Content-Type: application/json');

function json($data,$code=200){http_response_code($code);echo json_encode($data);exit;}

$input = json_decode(file_get_contents('php://input'),true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

switch($action){

  /* ---------- SIGN-UP ---------- */
  case 'signup':
    $name = trim($input['full_name']??'');
    $email= trim($input['email']??'');
    $pwd  = $input['password']??'';
    $cpwd = $input['confirm_password']??'';
    if($pwd!==$cpwd) json(['msg'=>'Passwords do not match'],400);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) json(['msg'=>'Invalid e-mail'],400);
    $hash = password_hash($pwd,PASSWORD_BCRYPT);
    $sql = "INSERT INTO users (full_name,email,password) VALUES (?,?,?)";
    $stmt=$pdo->prepare($sql);
    try{$stmt->execute([$name,$email,$hash]);json(['msg'=>'Account created – you can now log in']);}
    catch(PDOException $e){if($e->getCode()==23000) json(['msg'=>'E-mail already registered'],409); json(['msg'=>'Server error'],500);}
    break;

  /* ---------- LOGIN ---------- */
  case 'login':
    $email=trim($input['email']??'');
    $pwd  =$input['password']??'';
    $stmt=$pdo->prepare("SELECT id,full_name,password FROM users WHERE email=?");
    $stmt->execute([$email]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if($user && password_verify($pwd,$user['password'])){
      $_SESSION['user_id']=$user['id'];
      $_SESSION['user_name']=$user['full_name'];
      json(['msg'=>'Login OK','name'=>$user['full_name']]);
    }else json(['msg'=>'Wrong e-mail or password'],401);
    break;

  /* ---------- LOGOUT ---------- */
  case 'logout':
    session_destroy();
    json(['msg'=>'Logged out']);
    break;

  /* ---------- CHECK SESSION (for page load) ---------- */
  case 'check':
    if(isset($_SESSION['user_id'])){
      json(['logged'=>true,'name'=>$_SESSION['user_name']]);
    }else json(['logged'=>false]);
    break;

  /* ---------- PASSWORD RESET ---------- */
  case 'reset':
    $email=trim($input['email']??'');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) json(['msg'=>'Invalid e-mail'],400);
    $pdo->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);
    $token=bin2hex(random_bytes(20));
    $exp=date('Y-m-d H:i:s',strtotime('+1 hour'));
    $pdo->prepare("INSERT INTO password_resets (email,token,expires_at) VALUES (?,?,?)")
        ->execute([$email,$token,$exp]);
    $link = (isset($_SERVER['HTTPS'])?'https://':'http://').$_SERVER['HTTP_HOST']
            .dirname($_SERVER['SCRIPT_NAME'])."/reset_password.php?token=$token";
    json(['msg'=>"Reset link (demo): $link"]);
    break;

  default: json(['msg'=>'Unknown action'],400);
}
?>