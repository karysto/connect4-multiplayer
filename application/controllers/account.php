<?php

class Account extends CI_Controller {

  function __construct() {
    // Call the Controller constructor
    parent::__construct();
    session_start();
  }

  public function _remap($method, $params = array()) {
    // enforce access control to protected functions
    $protected = array('updatePasswordForm', 'updatePassword', 'logout');

    if (in_array($method, $protected) && !isset($_SESSION['user'])) {
      $this->session->set_flashdata('warning', 'You need to sign in first!');
      redirect('account/loginForm', 'refresh');
    }

    return call_user_func_array(array($this, $method), $params);
  }

  function loginForm() {
    $data = array(
      'title' => 'Sign In',
      'main' => 'account/loginForm'
    );
    $this->load->view('template', $data);
  }

  function login() {
    $this->load->library('form_validation');
    $this->form_validation->set_rules('username', 'Username', 'required');
    $this->form_validation->set_rules('password', 'Password', 'required');

    if ($this->form_validation->run() == FALSE) {
      $this->session->set_flashdata('error', 'The info you provided failed to validate.');
      redirect('account/loginForm', 'refresh');

    } else {
      $login = $this->input->post('username');
      $clearPassword = $this->input->post('password');

      $this->load->model('user_model');
      $user = $this->user_model->get($login);

      if (isset($user) && $user->comparePassword($clearPassword)) {
        $_SESSION['user'] = $user;

        $this->user_model->updateStatus($user->id, User::AVAILABLE);

        $this->session->set_flashdata('info', 'Welcome back!');
        redirect('arcade/index', 'refresh');
      }
      else {
        $this->session->set_flashdata('error', 'Incorrect username or password!');
        redirect('account/loginForm', 'refresh');
      }

    }
  }

  function logout() {
    $user = $_SESSION['user'];
    $this->load->model('user_model');
    $this->user_model->updateStatus($user->id, User::OFFLINE);
    session_destroy();
    redirect('', 'refresh');
  }

  function newForm() {
    $data = array(
      'title' => 'Register',
      'main' => 'account/newForm'
    );
    $this->load->view('template', $data);
  }

  function createNew() {
    $this->load->library('form_validation');
    $this->form_validation->set_rules('username', 'Username', 'required|is_unique[user.login]');
    $this->form_validation->set_rules('password', 'Password', 'required');
    $this->form_validation->set_rules('first', 'First', "required");
    $this->form_validation->set_rules('last', 'last', "required");
    $this->form_validation->set_rules('email', 'Email', "required|is_unique[user.email]");


    if ($this->form_validation->run() == FALSE) {
      $this->session->set_flashdata('error', 'The info you provided failed to validate.');
      redirect('account/newForm', 'refresh');

    } else {

      // Captcha
      include_once FCPATH . '/securimage/securimage.php';
      $securimage = new Securimage();
      if ($securimage->check($_POST['captcha_code']) == false) {
        // the code was incorrect
        $this->session->set_flashdata('error', "Your captcha input doesn't match! Please try again.");
        redirect('account/newForm', 'refresh');
        return;
      }

      $user = new User();

      $user->login = $this->input->post('username');
      $user->first = $this->input->post('first');
      $user->last = $this->input->post('last');
      $clearPassword = $this->input->post('password');
      $user->encryptPassword($clearPassword);
      $user->email = $this->input->post('email');

      $this->load->model('user_model');
      $error = $this->user_model->insert($user);

      $this->session->set_flashdata('info', 'Registration Complete. Please sign in again.');
      redirect('account/loginForm', 'refresh');
    }
  }

  function updatePasswordForm() {
    $data = array(
      'title' => 'Change Password',
      'main' => 'account/updatePasswordForm'
    );
    $this->load->view('template', $data);
  }

  function updatePassword() {
    $this->load->library('form_validation');
    $this->form_validation->set_rules('oldPassword', 'Old Password', 'required');
    $this->form_validation->set_rules('newPassword', 'New Password', 'required');

    if ($this->form_validation->run() == FALSE) {
      $this->session->set_flashdata('warning', 'The info you provided failed to validate.');
      redirect('account/updatePasswordForm', 'refresh');

    } else {
      $user = $_SESSION['user'];

      $oldPassword = $this->input->post('oldPassword');
      $newPassword = $this->input->post('newPassword');

      if ($user->comparePassword($oldPassword)) {
        $user->encryptPassword($newPassword);
        $this->load->model('user_model');
        $this->user_model->updatePassword($user);
        redirect('arcade/index', 'refresh');
      } else {
        $this->session->set_flashdata('error', "Passwords don't match.");
        redirect('account/updatePasswordForm', 'refresh');
      }
    }
  }

  function recoverPasswordForm() {
    $data = array(
      'title' => 'Password Recovery',
      'main' => 'account/recoverPasswordForm'
    );
    $this->load->view('template', $data);
  }

  function recoverPassword() {
    $this->load->library('form_validation');
    $this->form_validation->set_rules('email', 'email', 'required');

    if ($this->form_validation->run() == FALSE) {
      $this->session->set_flashdata('warning', 'The info you provided failed to validate.');
      redirect('account/recoverPasswordForm', 'refresh');

    } else {
      $email = $this->input->post('email');
      $this->load->model('user_model');
      $user = $this->user_model->getFromEmail($email);

      if (isset($user)) {
        $newPassword = $user->initPassword();
        $this->user_model->updatePassword($user);

        $this->load->library('email');

        $config['protocol']    = 'smtp';
        $config['smtp_host']    = 'ssl://smtp.gmail.com';
        $config['smtp_port']    = '465';
        $config['smtp_timeout'] = '7';
        // reuse the same account we made from A3
        $config['smtp_user']    = 'estore.mailer@gmail.com';
        $config['smtp_pass']    = 'IJustWannaUseGoogleSMTP';
        $config['charset']    = 'utf-8';
        $config['newline']    = "\r\n";
        $config['mailtype'] = 'text'; // or html
        $config['validation'] = TRUE; // bool whether to validate email or not

        $this->email->initialize($config);

        $this->email->from('csc309Login@cs.toronto.edu', 'Login App');
        $this->email->to($user->email);

        $this->email->subject('Password recovery');
        $this->email->message("Your new password is $newPassword");

        $result = $this->email->send();

        // $this->session->set_flashdata('info', $this->email->print_debugger(););

        $data = array(
          'title' => 'Check your email',
          'main' => 'account/emailPage'
        );
        $this->load->view('template', $data);
      }
      else {
        $this->session->set_flashdata('error', 'No record exists for this email!');
        redirect('account/recoverPasswordForm', 'refresh');
      }
    }
  }
}

