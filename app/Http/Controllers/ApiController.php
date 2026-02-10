<?php

namespace App\Http\Controllers;

use Str;
use URL;
use Auth;
use File;
use Hash;
use Mail;
use Request;
use Response;
use DateTime;
use App\Model\XApi;
use App\Model\MrDetail;
use App\Model\Stockiest;
use App\Model\UserToken;
use App\Model\AppVersion;
use App\Model\AllRequest;
use App\Model\MrTerritory;
use App\Model\MedicalStore;
use App\Model\DoctorDetail;
use App\Model\SalesHistory;
use App\Model\DoctorProfile;
use App\Model\DoctorOffset;
use App\Model\StockiestStatement;
use App\Model\MrWiseStockiestData;
use App\Model\MedicalStoreDoctorData;
use App\Model\DoctorRequestTerritorry;
use App\Model\StockiestWiseMedicalStoreData;

class ApiController extends GlobalController
{
    public function __construct(){
        
        $headers = apache_request_headers();
        //check X-API
        if (isset($headers['x-api-key']) && $headers['x-api-key'] != '') {
            if (isset($headers['device-type']) && $headers['device-type'] != '') {
               if(!XApi::checkXAPI($headers['x-api-key'],$headers['device-type'])){
                   echo json_encode(array('status'=>500,'message'=>'Invalid X-API'));exit;
               }
            } else {
               echo json_encode(array('status'=>500,'message'=>'Device type not found'));exit;
            }
        } else {
          echo json_encode(array('status'=>500,'message'=>'X-API key not found'));exit; 
        }

        //check version
        if (isset($headers['device-token']) && isset($headers['version'])) {
            $updateVersion = UserToken::where('Device_Token',$headers['device-token'])->update(['Version' => $headers['version']]);
        }

        if (isset($headers['device-type']) && isset($headers['version'])) {
            $getUserAppVersion = AppVersion::where('platform',$headers['device-type'])->where('version',$headers['version'])->first();
            if (!empty($getUserAppVersion->expireddate)) {
                if ($getUserAppVersion->expireddate < date('Y-m-d H:i:s')) {
                    //CHECK IF THE USER VERSION IS EXPIRED OR NOT
                    $res['status'] = 700;
                    $res['message'] = "App Version Expired";
                    echo json_encode($res);
                    exit;
                } 
            } 
        }  

    }

    public function nulltoblank($data) {

        return !$data ? $data = "" : $data;
        
    }

    //GENERATE DEVICE TOKEN
    public function generateDeviceToken(){
        
        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();
        
        if (!isset($headers['device-type']) && $headers['device-type'])
            $errors_array['device-type'] = 'Please pass device type';

        if(count($errors_array) == 0){
            $user = new MrDetail;
            $token = $user->generateToken($headers);
            $response['device_token'] = $token;

            $data = $response;
            $message = '';
            return $this->responseSuccess($message,$data);

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);
        }
    }

    //custom login api
    public function mrLogin(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!Request::has('email') || Request::get('email') == "")
            $errors_array['email'] = 'Please pass email id';

        if (!Request::has('password') || Request::get('password') == "")
            $errors_array['password'] = 'Please pass password';

        if(count($errors_array) == 0){
            $data = Request::all();

            $mr_data = array();

            //check member
            $mr_detail = MrDetail::where('email',$data['email'])->where('is_active','1')->where('is_delete','0')->first();
           
            if(!empty($mr_detail)){
                if(Hash::check($data['password'], $mr_detail->password)){

                        $update_member = MrDetail::findOrFail($mr_detail->id);
                        $update_member->is_login = 1;
                        $update_member->save();

                        $mr_data['user_id'] = $this->nulltoblank($mr_detail->id);
                        $mr_data['full_name'] = $this->nulltoblank($mr_detail->full_name);
                        $mr_data['email'] = $this->nulltoblank($mr_detail->email);
                        //update user app token
                        $app_token = $this->updateUserAppToken($headers,$mr_detail->id);
                        $mr_data['user_token'] = $app_token;
                    
                        
                        $data = $mr_data;
                        $message = 'Login successful!';
                        return $this->responseSuccess($message,$data);
                } else {

                    $message = 'Password incorrect!';
                    return $this->responseUnauthorized($message);
                }  

            } else {

                $message = 'User not found!';
                return $this->responseUnauthorized($message);

            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }

    }

    // Student forgot password Api
    public function mrForgotPassword(){

        $errors_array = array();

        if(!Request::has('email') || Request::get('email') == ""){
            $errors_array['email'] = 'Please enter your email ID.';
        }

        if(count($errors_array) == 0){
            $check_mail = MrDetail::where('email', Request::get('email'))->where('is_delete', 0)->first();
            if(!empty($check_mail)){

                $mr_data = MrDetail::where('email', Request::get('email'))->where('is_delete', 0)->first();

                $maildata[] = '';
                $signature_message = '';
                $mail_message = '';
                $mail_message .= "<b>Hello,</b><br><br>";
                $mail_message .= "Below is Your change password Link</b><br><br>";
                $mail_message .= "";
                $signature_message .= "<br>Regards</b>";
                $signature_message .= "<br>Kaps</b><br>";
                $title = "Change Password - Mr Forgot Password";
                $email = Request::get('email');

                $maildata['bodymessage'] = $mail_message;
                $maildata['signature_message'] = $signature_message;
                $maildata['mr_data'] = $mr_data;
                $token = app('auth.password.broker')->createToken($mr_data);
                $maildata['token'] = $token;

                Mail::send('mail.email', $maildata, function ($message) use ($email,$title)
                {
                    $message->from('darshit.finlark@gmail.com', 'forgot Password');
                    $message->to($email);
                    $message->subject($title);
                });

                $message = 'Forgot password link successfully send to registed email id';
                return $this->responseSuccess($message);

            } else {

                $message = 'Email id not available in our record!';
                return $this->responseUnauthorized($message);

            }
        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }

        
    }

    //member logout
    public function userLogout(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-token']) || $headers['user-token'] == "")
            $errors_array['user-token'] = 'Please enter user token';
        

        if(count($errors_array) == 0){

            $updateToken = UserToken::where('app_token',$headers['user-token'])
                                    ->update(['app_token' => '']);

            if($updateToken){

                $message = 'Successfully logout';
                return $this->responseSuccess($message);

            } else {

                $message = 'errors';
                return $this->responseFailer($message);
            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }      
    }

    //change password
    public function changePassword(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('current_password') || Request::get('current_password') == "")
            $errors_array['current_password'] = 'Please pass current password';

        if (!Request::has('password') || Request::get('password') == "")
            $errors_array['password'] = 'Please pass password';

        if (!Request::has('confirm_password') || Request::get('confirm_password') == "")
            $errors_array['confirm_password'] = 'Please pass confirm password';

        if(count($errors_array) == 0){

            $data = Request::all();

            //check email id
            $mr_data = MrDetail::where('id',$headers['user-id'])->where('is_delete', 0)->first();

            if(!empty($mr_data)){

                //check password
                if(Hash::check($data['current_password'], $mr_data->password)){

                    if($data['password'] == $data['confirm_password']){

                        $update_member = MrDetail::findOrFail($mr_data->id);
                        $update_member->password = Hash::make($data['confirm_password']);
                        $update_member->save();

                        if($update_member){

                            $message = 'Password updated Successfully!';
                            return $this->responseSuccess($message);

                        } else {

                            $message = 'Something went wrong!';
                            return $this->responseFailer($message);                        

                        }    

                    } else {

                        $message = 'Password and confirm password not match!';
                        return $this->responseUnauthorized($message);

                    }
                    
                } else {

                    $message = 'Current password incorrect!';
                    return $this->responseUnauthorized($message);

                }

            } else {

                $message = 'Unauthorized User!';
                return $this->responseUnauthorized($message);
                    
            }

        }  else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }      
    }

    //stockiest list
    public function stockistList(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if(count($errors_array) == 0){

            $data = Request::all();

            //search stockist name
            $query = MrWiseStockiestData::query();
            $query->where('mr_id',$headers['user-id']);               
            $query->where('is_delete',0);
            $query->with(['stockiest_detail','medical_store','doctor']);
            if(isset($data['stockist_name']) && $data['stockist_name'] != ''){
                $stockist_name = $data['stockist_name'];
                $query->whereHas('stockiest_detail', function ($query) use ($stockist_name) { $query->where('stockiest_name', 'like','%' . $stockist_name . '%'); });

            } else {

                $query->with(['stockiest_detail']);
            }

            //if date filter require
            if(isset($data['sales_month']) && $data['sales_month'] != ''){
                $date = explode('-',$data['sales_month']);
                $query->whereMonth('sales_month', '=', $date[0]);
                $query->whereYear('sales_month', '=', $date[1]);
            }else{
                $current_month = date('m');
                $current_year = date('Y');
                $query->whereMonth('sales_month', '=', $current_month);
                $query->whereYear('sales_month', '=', $current_year);                
            }

            $get_stockiest_data = $query->paginate(20);
  
            $check_monthly_confirm = '';
            $completed = '';
            if(!empty($get_stockiest_data)){
                $stockiest_data = array();

                foreach ($get_stockiest_data as $sk => $sv) {
                    
                    $stockiest_data[$sk]['id'] = $this->nulltoblank($sv->id);
                    $stockiest_data[$sk]['stockist_id'] = $this->nulltoblank($sv->stockiest_id);
                    $stockiest_data[$sk]['stockist_name'] = $this->nulltoblank($sv['stockiest_detail']['stockiest_name']);
                    $stockiest_data[$sk]['sales_month'] = $this->nulltoblank(date('F Y',strtotime($sv->sales_month)));
                    $stockiest_data[$sk]['amount'] = ($sv->amount != '' && $sv->amount !=  0) ? (string)$sv->amount : (($sv->amount === null && $sv->amount !== 0) ? "" :  0);
                    
                    // $stockiest_data[$sk]['entry_status'] = ($sv->entry_status == 0) ? 0 : (($sv->entry_status == 1) ? 1 : 2);
                    // $stockiest_data[$sk]['is_completed'] = $sv->is_completed == 0 ? 0 : 1;    

                    //0 = blank, 1 = draft, 2 = completed
                    if($sv->is_completed == 2){
                        $stockiest_data[$sk]['stockist_status'] = 2;         
                    }else{
                        if($sv->priority == 0){
                            $stockiest_data[$sk]['stockist_status'] = $sv->priority == 0 ? 0 : 1;                            
                        }elseif($sv->is_confirm_data == 1){
                            $stockiest_data[$sk]['stockist_status'] = 2;                            
                        }else{
                            $stockiest_data[$sk]['stockist_status'] = $sv->priority == 0 ? 0 : 1;                            
                        }
                        
                    }

                    if($sv->is_completed == 2){
                        $stockiest_data[$sk]['medical_store_status'] = 2;    
                    }elseif(!$sv['medical_store']->isEmpty()){
                        if($sv->is_confirm_data == 1){
                            $stockiest_data[$sk]['medical_store_status'] = 2;                            
                        }else{
                            $stockiest_data[$sk]['medical_store_status'] = 1;
                        }
                        
                    }else{
                        $stockiest_data[$sk]['medical_store_status'] = 0;
                    }


                    if($sv->is_completed == 2){
                        $stockiest_data[$sk]['doctor_status'] = 2;    
                    }elseif(!$sv['doctor']->isEmpty()){
                        if($sv->is_confirm_data == 1){
                            $stockiest_data[$sk]['doctor_status'] = 2;                            
                        }else{
                            $stockiest_data[$sk]['doctor_status'] = 1;
                        }
                    }else{
                        $stockiest_data[$sk]['doctor_status'] = 0;
                    }
                    
                    //button status
                    if(($sv->is_completed == 0)){
                        $stockiest_data[$sk]['button_status'] = 0; //disable 
                    }elseif(($sv->is_completed == 1)){
                        $stockiest_data[$sk]['button_status'] = 1; //enable
                    }else{
                        $stockiest_data[$sk]['button_status'] = 2; //enable
                    }

                    // if($sv->is_confirm_data == 1){
                    //     $stockiest_data[$sk]['stockist_status'] = 2;
                    //     $stockiest_data[$sk]['medical_store_status'] = 2;    
                    //     $stockiest_data[$sk]['doctor_status'] = 2;    
                    // }

                    //data 0 = not confirm 1 = confirm
                    // $stockiest_data[$sk]['month_confirm_status'] = $sv->is_confirm_data == 0 ? 0 : 1;
                    // $check_monthly_confirm = $sv->is_confirm_data == 0 ? 0 : 1;                        
                }
                
                $data['total_stockist'] = $get_stockiest_data->total();
                if(isset($data['sales_month']) && $data['sales_month'] != ''){
                    // $date = explode('-',$data['sales_month']);
                    $check = MrWiseStockiestData::where('mr_id',$headers['user-id'])->where('is_delete',0)->whereMonth('sales_month', '=', $date[0])->whereYear('sales_month', '=', $date[1])->first();
                    if(!empty($check)){
                        $data['month_confirm_status'] = $check->is_confirm_data == 0 ? 0 : 1;                            
                    }else{
                        $data['month_confirm_status'] = 0; 
                    }
                    
                }
                if(!empty($stockiest_data)){
                    $data['stockist'] = $stockiest_data;
                    $message = '';
                    return $this->responseSuccess($message,$data);    
                }else{
                    $data['stockist'] = $stockiest_data;
                    $message = '';
                    $this->LogOutput(Response::json(array('status'=>'success','status_code' => 201,'data' => $data)));
                    return Response::json(array('status'=>'success','status_code' => 201,'data' => $data),200);
                }
                
                if(!empty($stockiest_data)){
                    $data['stockist'] = $stockiest_data;
                    $message = '';
                    return $this->responseSuccess($message,$data);    
                }else{
                    $data['stockist'] = [];
                    $message = 'stockist not found!';
                    return $this->responseDatanotFound($message,$data);

                }

            } else {

                $message = 'Stockiest not found!';
                return $this->responseDatanotFound($message);

                /*$message = 'Stockiest not found!';
                return $this->responseFailer($message);*/

            }   

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }
    }

    //update stockiest data
    public function updateStatus(){
        
        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('stockist_id') || Request::get('stockist_id') == "")
            $errors_array['stockist_id'] = 'Please pass stockist id';

        if(count($errors_array) == 0){

            $data = Request::all();

            $update_status = MrWiseStockiestData::findOrFail($data['stockist_id']);

            //update status of entry
            if(isset($data['entry_status']) && $data['entry_status'] != ''){

                $update_status->entry_status = $data['entry_status'];

            }

            if(isset($data['is_completed']) && $data['is_completed'] != ''){
                
                if(!Request::has('amount')){
                    
                    $message = 'Pass amount!';
                    return $this->responseFailer($message);

                }else{

                    $update_status->amount = $data['amount'];
                    $update_status->submitted_on = date("Y-m-d");
                    $update_status->priority = 1;           
                    $update_status->submitted_by = $headers['user-id'];           
                    $update_status->is_completed = $data['is_completed'];

                }

            }

            $update_status->save();

            if($update_status){

                $message = 'Successfully updated!';
                return $this->responseSuccess($message);

            } else {

                $message = 'Something went wrong!';
                return $this->responseFailer($message);

            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);            

        }        
    }

    //upload documents of stockiest
    public function uploadDocument(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('stockist_id') || Request::get('stockist_id') == "")
            $errors_array['stockist_id'] = 'Please pass stockist id';

        if (!Request::has('statement'))
            $errors_array['statement'] = 'Please pass statement';  

        if(count($errors_array) == 0){

            $data = Request::all();
            
            foreach ($data['statement'] as $sk => $sv) {
                $save_statement = new StockiestStatement;
                $save_statement->data_id = $data['stockist_id'];   
                $statement = $this->uploadImage($sv,'statement');
                $save_statement->statement = $statement;  
                $save_statement->save();

            }

            $message = 'Statement successfully uploaded!';
            return $this->responseSuccess($message);
            
        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }   
    }

    //stockist list confirm button
    /*public function stockistConfirm(){
        
        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('sales_month') || Request::get('sales_month') == "")
            $errors_array['sales_month'] = 'Please pass sales_month';

        if(count($errors_array) == 0){

            $data = Request::all();

            $query = MrWiseStockiestData::query();
                $date = explode('-',$data['sales_month']);
                $query->whereMonth('sales_month', '=', $date[0]);
            $query->whereYear('sales_month', '=', $date[1]);
            $get = $query->update(['is_confirm_data' => 1]);
            
            if(isset($get)){

                $message = 'Monthly data successfully confirm!';
                return $this->responseSuccess($message);

            }else{

                $message = 'Something went wrong!';
                return $this->responseFailer($message);

            }

        }else{
            
            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }

    }*/

    //medical store list
    public function medicalstoreList(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('stockist_id') || Request::get('stockist_id') == "")
            $errors_array['stockist_id'] = 'Please pass stockist id';

        if(count($errors_array) == 0){

            $data = Request::all();

            //if medical store not in database
            //================================            
            $date = explode(' ',$data['sales_month']);
            $date_month = date("m", strtotime($date[0]));
            $current_month = date('m');
            $current_year = date('Y');
           
            //check month
            // if(($date_month == $current_month) && ($date[1] == $current_year)){
                //get mr territories
                $get_mr_territories = SalesHistory::with(['mr_detail' => function($q){ $q->with(['get_territory']); } ,'user_detail'])->whereYear('sales_month','=',$date[1])->whereMonth('sales_month','=',$date_month)->where('mr_id',$headers['user-id'])->first();
                
                //mr territories and sub territories
                $territories = array();
                $sub_territories = array();
                if(!empty($get_mr_territories['mr_detail']['get_territory'])){
                    
                    foreach ($get_mr_territories['mr_detail']['get_territory'] as $tk => $tv) {

                        $territories[] = $tv['territories_id'];
                        $sub_territories[] = $tv['sub_territories'];

                    }
                }

                //medical stores
                $get_medical_store = MedicalStore::with(['mendical_store_territories'])->where('is_delete',0)->get();

                //get stockiest depend on mr territories
                $store_id = array();
                if(!empty($get_medical_store)){
                    foreach ($get_medical_store as $sk => $sv) {
                        foreach ($sv['mendical_store_territories'] as $tk => $tv) {
                            if(in_array($tv['territories_id'],$territories) && in_array($tv['sub_territories'],$sub_territories)){
                                $store_id[] = $sv->id;
                            }
                        }
                    }
                }

                $territorist_medical_store = MedicalStore::whereIn('id',$store_id)->with(['medical_store_user','mendical_store_territories'])->where('is_delete',0)->get();

                $check_data = array();
                if(!empty($get_mr_territories)){
                    $check_data = StockiestWiseMedicalStoreData::where('stockiest_id',$data['stockist_id'])->where('mr_id',$headers['user-id'])->where('sales_month',$get_mr_territories->sales_month)->first();    
                }
            
                $sales_id = MrWiseStockiestData::where('id',$data['stockist_id'])->where('mr_id',$headers['user-id'])->first();

                //save stockiest data
                if(empty($check_data)){
                    if(!empty($territorist_medical_store) && (!empty($sales_id))){

                        foreach($territorist_medical_store as $sk => $sv) {
                            $save_stockiest = new StockiestWiseMedicalStoreData;
                            $save_stockiest->medical_store_id = $sv->id;
                            $save_stockiest->stockiest_id = $data['stockist_id'];
                            $save_stockiest->sales_id = $sales_id->sales_id;
                            $save_stockiest->mr_id = $headers['user-id'];
                            $save_stockiest->sales_month = $get_mr_territories->sales_month;
                            $save_stockiest->priority = 0;
                            $save_stockiest->save();
                        }
                    }
                }
            // }

            //doctors detail
            $get_doctor = DoctorDetail::with(['get_territory'])->where('is_delete',0)->get();

            //get doctor depend on mr territories
            $doctor_id = array();
            if(!empty($get_doctor)){
                foreach ($get_doctor as $sk => $sv) {
                    foreach ($sv['get_territory'] as $tk => $tv) {
                        if(in_array($tv['territories_id'],$territories) && in_array($tv['sub_territories'],$sub_territories)){
                            $doctor_id[] = $sv->id;
                        }
                    }
                }
            }
           
            $territorist_doctor_detail = DoctorProfile::whereIn('doctor_id',$doctor_id)->where('is_delete',0)->count();
            //endif
            //================================

            $query = StockiestWiseMedicalStoreData::query();
            $query->where('mr_id',$headers['user-id']);  
            $query->where('stockiest_id',$data['stockist_id']);   
            $query->where('is_delete',0);
            $query->whereMonth('sales_month',$date_month);
            $query->whereYear('sales_month',$date[1]);
            if(isset($data['medical_store_name']) && $data['medical_store_name'] != ''){
                $medical_store_name = $data['medical_store_name'];
                $query->whereHas('store_detail', function ($query) use ($medical_store_name) { $query->where('store_name', 'like','%' . $medical_store_name . '%'); })->where('stockiest_id',$data['stockist_id']);

            } else {

                $query->with(['store_detail']);
            }
            $get_medical_store_data = $query->paginate(10);

            if(!empty($get_medical_store_data)){
                $get_medical_store_detail = array();
                foreach ($get_medical_store_data as $mk => $mv) {
                    
                    $get_medical_store_detail[$mk]['id'] = $this->nulltoblank($mv->id);
                    $get_medical_store_detail[$mk]['store_name'] = $this->nulltoblank($mv['store_detail']['store_name']);
                    $get_medical_store_detail[$mk]['sales_amount'] = $this->nulltoblank($mv->sales_amount);

                    $query = MedicalStoreDoctorData::query();
                    $query->where('medical_store_id',$mv->id);  
                    $query->where('is_delete',0);
                    $total_doctor = $query->count();//all doctors

                    $query->whereNOTNULL('sales_amount');
                    $remaining_doctor = $query->count();//remaining doctors

                    $get_medical_store_detail[$mk]['sales_amount'] = ($mv->sales_amount != '' && $mv->sales_amount !=  0) ? (string)$mv->sales_amount : (($mv->sales_amount === null && $mv->sales_amount !== 0) ? "" :  (string)0);
                                       
                    $get_medical_store_detail[$mk]['extra_business'] = ($mv->extra_business != '' && $mv->extra_business !=  0) ? (string)$mv->extra_business : (($mv->extra_business === null && $mv->extra_business !== 0) ? "" :  (string)0);

                    $get_medical_store_detail[$mk]['scheme_business'] = ($mv->scheme_business != '' && $mv->scheme_business !=  0) ? (string)$mv->scheme_business : (($mv->scheme_business === null && $mv->scheme_business !== 0) ? "" :  (string)0);

                    $get_medical_store_detail[$mk]['ethical_business'] = ($mv->ethical_business != '' && $mv->ethical_business !=  0) ? (string)$mv->ethical_business : (($mv->ethical_business === null && $mv->ethical_business !== 0) ? "" :  (string)0);

                    $total_amount = $mv->sales_amount + $mv->extra_business + $mv->scheme_business + $mv->ethical_business;
                    $get_medical_store_detail[$mk]['total_amount'] = $this->nulltoblank((string)$total_amount);
                    
                    $doctor = (($total_doctor == 0 ) ? $territorist_doctor_detail : $total_doctor);
                    if($doctor == 0 && $remaining_doctor == 0){
                        $get_medical_store_detail[$mk]['remaining_doctor'] = "";    
                    }else{
                        $get_medical_store_detail[$mk]['remaining_doctor'] = $remaining_doctor.'/'.(($total_doctor == 0 ) ? $territorist_doctor_detail : $total_doctor);
                    }
                    
                    
                    $get_medical_store_detail[$mk]['entry_status'] = (($mv->entry_status == 2 ) ? $mv->entry_status : $mv->priority);
                    $get_medical_store_detail[$mk]['doctors_show'] = $mv->priority == 0 ? 0 : 1; 

                }

                $sales_id = MrWiseStockiestData::where('id',$data['stockist_id'])->where('mr_id',$headers['user-id'])->whereMonth('sales_month', '=', $date_month)->whereYear('sales_month', '=', $date[1])->first();
                
                $data['confirm_data'] = (!empty($sales_id) && $sales_id->is_completed == 0) ? 0 : (!empty($sales_id) && ($sales_id->is_completed == 1)  ? 1 : 2);
                
                if(isset($sales_id->is_confirm_data) && $sales_id->is_confirm_data == 1){
                    $data['confirm_data'] = 2;
                }

                if(!empty($get_medical_store_detail)){
                    $data['total_store'] = $get_medical_store_data->total();
                    $data['stores'] = $get_medical_store_detail;
                    $message = '';
                    return $this->responseSuccess($message,$data);
                }else{
                    // $data['total_store'] = $get_medical_store_data->total();
                    $data['stores'] = [];
                    $message = 'Medicalstore not found!';
                    return $this->responseDatanotFound($message,$data);

                }
                
            }else{

                /*$message = 'Stockiest not found!';
                return $this->responseFailer($message);*/

                $message = 'Medicalstore not found!';
                return $this->responseDatanotFound($message);

            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }
    }

    //update amount of medicalstore
    public function updateAmountMedicalstore(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('stockist_id') || Request::get('stockist_id') == "")
            $errors_array['stockist_id'] = 'Please pass stockist id';

        if (!Request::has('store_id') || Request::get('store_id') == "")
            $errors_array['store_id'] = 'Please pass store id';

        if(count($errors_array) == 0){
            $data = Request::all();

            $get_stockist_data = MrWiseStockiestData::where('id',$data['stockist_id'])->first();

            $query = StockiestWiseMedicalStoreData::where('stockiest_id',($data['stockist_id']));

            //store sales amount
            $store_sales_amount = $query->sum( 'sales_amount' );

            //extra business
            $extra_business = $query->sum( 'extra_business' );

            //scheme business
            $scheme_business = $query->sum( 'scheme_business' );

            //ethical business
            $ethical_business = $query->sum( 'ethical_business' );

            //check same value
            $check_same_value = $query->where('id',$data['store_id'])->first();  
            
            if((isset($data['sales_amount']) && $data['sales_amount'] != '') && (isset($check_same_value->sales_amount) && ($check_same_value->sales_amount != ''))){
                $amount = $check_same_value->sales_amount;
                $request_amount = $data['sales_amount'];   
            }else{
                $amount = 0;
                $request_amount = 0;
            }

            if((isset($data['extra_business']) && $data['extra_business'] != '') && (isset($check_same_value->extra_business) && ($check_same_value->extra_business != ''))) {
                $amount = $check_same_value->extra_business;
                $request_amount = $data['extra_business'];
            }else{
                $amount = 0;
                $request_amount = 0;
            }

            if((isset($data['scheme_business']) && $data['scheme_business'] != '')  && (isset($check_same_value->scheme_business) && ($check_same_value->scheme_business != ''))){
                $amount = $check_same_value->scheme_business;
                $request_amount = $data['scheme_business'];
            }else{
                $amount = 0;
                $request_amount = 0;
            }

            //ethical business
            if((isset($data['ethical_business']) && $data['ethical_business'] != '')  && (isset($check_same_value->ethical_business) && ($check_same_value->ethical_business != ''))){
                $amount = $check_same_value->ethical_business;     
                $request_amount = $data['ethical_business'];
            }else{
                $amount = 0;
                $request_amount = 0;
            }

            //store total amount
            $store_total_amount = $store_sales_amount + $extra_business + $scheme_business + $ethical_business;
            
            if($amount != 0){
                
                $add_new_amount = $store_total_amount + $amount - $check_same_value->sales_amount - $check_same_value->extra_business - $check_same_value->scheme_business - $check_same_value->ethical_business + $data['sales_amount'] + $data['extra_business'] + $data['scheme_business'] + $data['ethical_business'];

            }else{
                
                $add_new_amount = $store_total_amount;
                // $add_new_amount = $store_total_amount - $amount + $request_amount + $data['sales_amount'] + $data['extra_business'] + $data['scheme_business'] + $data['ethical_business'];
                $add_new_amount = $data['sales_amount'] + $data['extra_business'] + $data['scheme_business'] + $data['ethical_business'];
                
            }
            
            if($add_new_amount > $get_stockist_data->amount){
                
                $message = 'Amount exceeded total sales!';
                return $this->responseFailer($message);

            }else{

                $update_amount = StockiestWiseMedicalStoreData::findOrFail($data['store_id']);

                //update status of entry
                if(isset($data['sales_amount']) && $data['sales_amount'] != ''){
                    $update_amount->sales_amount = $data['sales_amount'];
                    $update_amount->priority = 1;
                }

                if(isset($data['extra_business']) && $data['extra_business'] != ''){
                    $update_amount->extra_business = $data['extra_business'];                
                    $update_amount->priority = 1;
                }

                if(isset($data['scheme_business']) && $data['scheme_business'] != ''){
                    $update_amount->scheme_business = $data['scheme_business'];   
                    $update_amount->priority = 1;
                }

                if(isset($data['ethical_business']) && $data['ethical_business'] != ''){
                    $update_amount->ethical_business = $data['ethical_business'];   
                    $update_amount->priority = 1;
                }
                
                $update_amount->submitted_on = date("Y-m-d");
                $update_amount->submitted_by = $headers['user-id'];
                $update_amount->save();

                if($update_amount){

                    $message = 'Amount successfully updated!';
                    return $this->responseSuccess($message);  

                } else {

                    $message = 'Something went wrong!';
                    return $this->responseFailer($message);

                }

            }
            
        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }
        
    }

    //stockiest wise doctor list
    public function doctorsList(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('stockist_id') || Request::get('stockist_id') == "")
            $errors_array['stockist_id'] = 'Please pass stockist id';

        if (!Request::has('store_id') || Request::get('store_id') == "")
            $errors_array['store_id'] = 'Please pass store id';

        if(count($errors_array) == 0){
            $data = Request::all();

            //if doctor not in database
            //================================            
            $date = explode(' ',$data['sales_month']);
            $date_month = date("m", strtotime($date[0]));
            $current_month = date('m');
            $current_year = date('Y');

            //check month
            // if(($date_month == $current_month) && ($date[1] == $current_year)){
                //get mr territories
                $get_mr_territories = SalesHistory::with(['mr_detail' => function($q){ $q->with(['get_territory']); } ,'user_detail'])->whereYear('sales_month','=',$date[1])->whereMonth('sales_month','=',$date_month)->where('mr_id',$headers['user-id'])->first();

                
                //mr territories and sub territories
                $territories = array();
                $sub_territories = array();
                if(!empty($get_mr_territories['mr_detail']['get_territory'])){
                    
                    foreach ($get_mr_territories['mr_detail']['get_territory'] as $tk => $tv) {

                        $territories[] = $tv['territories_id'];
                        $sub_territories[] = $tv['sub_territories'];

                    }
                }

                //doctors detail
                $get_doctor = DoctorDetail::with(['get_territory'])->where('is_delete',0)->get();

                //get doctor depend on mr territories
                $doctor_id = array();
                if(!empty($get_doctor)){
                    foreach ($get_doctor as $sk => $sv) {
                        foreach ($sv['get_territory'] as $tk => $tv) {
                            if(in_array($tv['territories_id'],$territories) && in_array($tv['sub_territories'],$sub_territories)){
                                $doctor_id[] = $sv->id;
                            }
                        }
                    }
                }
               
                $territorist_doctor_detail = DoctorProfile::whereIn('doctor_id',$doctor_id)->where('is_delete',0)->get();

                if(!empty($get_mr_territories)){
                    $check_data = MedicalStoreDoctorData::where('medical_store_id',$data['store_id'])->where('sales_month',$get_mr_territories->sales_month)->first();
                }else{
                    $check_data = array();
                }

                $sales_id = MrWiseStockiestData::where('id',$data['stockist_id'])->where('mr_id',$headers['user-id'])->first();

                //save stockiest data
                if(empty($check_data) && (!empty($sales_id))){
                    if(!empty($territorist_doctor_detail)){

                        foreach($territorist_doctor_detail as $sk => $sv) {
                            $save_doctor_detail = new MedicalStoreDoctorData;
                            $save_doctor_detail->doctor_profile = $sv->id;
                            $save_doctor_detail->doctor_id = $sv->doctor_id;
                            $save_doctor_detail->medical_store_id = $data['store_id'];
                            $save_doctor_detail->stockiest_id = $data['stockist_id'];
                            $save_doctor_detail->sales_id = $sales_id->sales_id;
                            $save_doctor_detail->mr_id = $headers['user-id'];
                            $save_doctor_detail->sales_month = $get_mr_territories->sales_month;
                            $save_doctor_detail->priority = 0;
                            $save_doctor_detail->save();
                        }
                    }
                }
            // }
            //endif
            //================================

            $query = MedicalStoreDoctorData::query();
            $query->where('mr_id',$headers['user-id']);               
            $query->where('stockiest_id',$data['stockist_id']);    
            $query->where('medical_store_id',$data['store_id']);               
            $query->where('is_delete',0);
            $query->with(['stockiest_detail']);
            $query->whereMonth('sales_month',$date_month);
            $query->whereYear('sales_month',$date[1]);
            if(isset($data['doctor_name']) && $data['doctor_name'] != ''){

                $doctor_name = $data['doctor_name'];
                $query->whereHas('doctor_detail', function ($query) use ($doctor_name) { $query->where('full_name', 'like','%' . $doctor_name . '%'); })->orWhereHas('profile_detail', function ($query) use ($doctor_name) { $query->where('profile_name', 'like','%' . $doctor_name . '%'); })->where('medical_store_id',$data['store_id'])->where('is_delete',0);

            } else {

                $query->with(['doctor_detail','profile_detail']);

            }
         
            $get_doctor_data = $query->paginate(20);

            $query = MedicalStoreDoctorData::query();
            $query->where('mr_id',$headers['user-id']);               
            $query->where('stockiest_id',$data['stockist_id']);    
            $query->where('medical_store_id',$data['store_id']);               
            $query->where('is_delete',0);
            $query->with(['stockiest_detail']);
            $query->whereMonth('sales_month',$date_month);
            $query->whereYear('sales_month',$date[1]);
            $total_doctor = $query->count();//all doctors
            
            $query = MedicalStoreDoctorData::query();
            $query->where('mr_id',$headers['user-id']);    
            $query->where('stockiest_id',$data['stockist_id']);    
            $query->where('medical_store_id',$data['store_id']);   
            $query->whereMonth('sales_month',$date_month);
            $query->whereYear('sales_month',$date[1]);
            $query->where('sales_amount','!=','');
            $remaining_doctor = $query->count();//remaining doctors
           
            if(!empty($get_doctor_data)){
                $doctor_data = array();

                foreach ($get_doctor_data as $dk => $dv) {
                
                    $doctor_data[$dk]['id'] = $this->nulltoblank($dv->id);
                    $doctor_data[$dk]['store_id'] = $this->nulltoblank($dv->medical_store_id);
                    $doctor_data[$dk]['stockist_id'] = $this->nulltoblank($dv->stockiest_id);
                    if($dv['profile_detail']['profile_name'] != ''){
                        $doctor_data[$dk]['doctor_name'] = $this->nulltoblank($dv['doctor_detail']['full_name'] .'('. $dv['profile_detail']['profile_name'] .')' );    
                    }else{
                        $doctor_data[$dk]['doctor_name'] = $this->nulltoblank($dv['doctor_detail']['full_name']);    
                    }
                    
                    $doctor_data[$dk]['sales_month'] = $this->nulltoblank(date('F Y',strtotime($dv->sales_month)));
                    $doctor_data[$dk]['sales_amount'] = ($dv->sales_amount != '' && $dv->sales_amount !=  0) ? (string)$dv->sales_amount : (($dv->sales_amount === null && $dv->sales_amount !== 0) ? "" :  (string)0);

                }
                
                if(!empty($doctor_data)){
                    $data['total_doctor'] = $get_doctor_data->total();
                    $data['remaining_doctor'] = $remaining_doctor.'/'.$total_doctor;
                    $data['doctors'] = $doctor_data;
                    $message = '';
                    return $this->responseSuccess($message,$data);
                }else{
                    // $data['total_doctor'] = $get_doctor_data->total();
                    // $data['remaining_doctor'] = $remaining_doctor.'/'.$total_doctor;
                    $data['doctors'] = [];
                    $message = 'Requests not found!';
                    return $this->responseDatanotFound($message,$data);
                }

            } else {

                /*$message = 'Doctors not found!';
                return $this->responseFailer($message);*/

                $message = 'Doctors not found!';
                return $this->responseDatanotFound($message);

            } 

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }
    }

    //update doctor amount
    //old code for single doctor amount store
    /*public function updateDoctorSales(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('store_id') || Request::get('store_id') == "")
            $errors_array['store_id'] = 'Please pass store id';

        if (!Request::has('doctor_id') || Request::get('doctor_id') == "")
            $errors_array['doctor_id'] = 'Please pass doctor id';

        if (!Request::has('amount') || Request::get('amount') == "")
            $errors_array['amount'] = 'Please pass amount';

        if (!Request::has('entry_status') || Request::get('entry_status') == "")
            $errors_array['entry_status'] = 'Please pass entry status';

        if(count($errors_array) == 0){
            $data = Request::all();

            $store_entry_update = StockiestWiseMedicalStoreData::where('id',$data['store_id'])->first();

            $query = MedicalStoreDoctorData::where('medical_store_id',$data['store_id']);

            //store sales amount
            $store_sales_amount = $query->sum( 'sales_amount' );

            $check_same_value = $query->where('id',$data['doctor_id'])->first();  

            //sum of other bussiness
            $total_amount = $store_entry_update->sales_amount + $store_entry_update->extra_business + $store_entry_update->scheme_business  + $store_entry_update->ethical_business;

            if(empty($check_same_value)){
                $add_new_amount = $store_sales_amount + $data['amount'];

            }else{
                $add_new_amount = $store_sales_amount - $check_same_value->sales_amount + $data['amount'];
            }

            if($add_new_amount > $total_amount){
            
                $message = 'Amount exceeded total sales!';
                return $this->responseFailer($message);

            }else{   

                $update_amount = MedicalStoreDoctorData::findOrFail($data['doctor_id']);
                $update_amount->sales_amount = $data['amount'];
                $update_amount->submitted_by = $headers['user-id']; 
                $update_amount->submitted_on = date("Y-m-d");
                $update_amount->priority = 1;
                $update_amount->save();

                //change entry status
                $store_entry_update = StockiestWiseMedicalStoreData::where('id',$data['store_id'])->update(['entry_status' => $data['entry_status']]);

                if($update_amount){

                    $message = 'Amount successfully updated!';
                    return $this->responseSuccess($message);  

                } else {

                    $message = 'Something went wrong!';
                    return $this->responseFailer($message);

                }
                            

            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }
    }*/

    //updated code for multiple doctor amount store
    public function updateDoctorSales(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('store_id') || Request::get('store_id') == "")
            $errors_array['store_id'] = 'Please pass store id';

        if (!Request::has('doctor_id') || Request::get('doctor_id') == "")
            $errors_array['doctor_id'] = 'Please pass doctor id';

        if (!Request::has('amount') || Request::get('amount') == "")
            $errors_array['amount'] = 'Please pass amount';

        if (!Request::has('entry_status') || Request::get('entry_status') == "")
            $errors_array['entry_status'] = 'Please pass entry status';

        if(count($errors_array) == 0){
            $data = Request::all();

            $data_amount = array_sum($data['amount']);

            $store_entry_update = StockiestWiseMedicalStoreData::where('id',$data['store_id'])->first();

            $query = MedicalStoreDoctorData::where('medical_store_id',$data['store_id']);

            //store sales amount
            $store_sales_amount = $query->sum( 'sales_amount' );

            $check_same_value = $query->where('id',$data['doctor_id'])->first();  

            //sum of other bussiness
            $total_amount = $store_entry_update->sales_amount /*+ $store_entry_update->extra_business + $store_entry_update->scheme_business  + $store_entry_update->ethical_business*/;
            
            $add_new_amount = $data_amount;
     
            if($add_new_amount > $total_amount){
            
                $message = 'Amount exceeded total sales!';
                return $this->responseFailer($message);

            }else{   

                $all_data = array();

                if(!empty($data['doctor_id'])){
                    foreach ($data['doctor_id'] as $dk => $dv) {
                        $all_data[$dk]['doctor'] = $dv;
                    }
                }

                if(!empty($data['amount'])){
                    foreach ($data['amount'] as $ak => $av) {
                        $all_data[$ak]['amount'] = $av;
                    }
                }

                if(!empty($all_data)){

                    foreach ($all_data as $ak => $av) {
                        
                        $update_amount = MedicalStoreDoctorData::findOrFail($av['doctor']);
                        $old_amount = $update_amount->sales_amount;
                        $update_amount->sales_amount = ($av['amount'] != '' && $av['amount'] !=  0) ? $av['amount'] : (($av['amount'] === null && $av['amount'] !== 0) ? NULL : (($av['amount'] == '') ? '' :  0));
                        $update_amount->submitted_by = $headers['user-id']; 
                        $update_amount->submitted_on = date("Y-m-d");
                        $current_date = $update_amount->sales_month;
                        $update_amount->priority = 1;
                        if($update_amount->isDirty()){   
                            $update_amount->previous_sales_amount = $old_amount;
                            $update_amount->is_considered = 0;
                        }
                        $update_amount->save();    
                    }
                }
                
                $stockiest_id = $update_amount->stockiest_id;
                //for offset
                if(!empty($all_data)){

                    foreach ($all_data as $ak => $av) {
                        
                        // $current_date = date("Y-m-d");
                        //get doctor id
                        $get_doctor_detail = MedicalStoreDoctorData::with(['commission'])->where('id',$av['doctor'])->first();
                        
                        //check considered
                        $get_doctor_sales = MedicalStoreDoctorData::where('doctor_profile',$get_doctor_detail->doctor_profile)->where('doctor_id',$get_doctor_detail->doctor_id)->where('stockiest_id',$stockiest_id)->where('is_considered',0)->first();

                        
                        if(!empty($get_doctor_sales)){
                                
                            //get total sales of doctor
                            $get_doctor_sales = MedicalStoreDoctorData::where('doctor_profile',$get_doctor_detail->doctor_profile)->where('doctor_id',$get_doctor_detail->doctor_id)->where('stockiest_id',$stockiest_id)->where('is_considered',0)->sum('sales_amount');
                            
                            //get last monthsales
                            $get_doctor_offset = DoctorOffset::where('profile_id',$get_doctor_detail->doctor_profile)->where('doctor_id',$get_doctor_detail->doctor_id)->orderBy('id','DESC')->first();

                            $amount = ($av['amount'] != '' && $av['amount'] !=  0) ? $av['amount'] : (($av['amount'] === null && $av['amount'] !== 0) ? 0 :  0);

                            //net sales 
                            $previous_sales = $get_doctor_sales - $amount;
                            
                            $previous_store_sales = MedicalStoreDoctorData::where('doctor_profile',$get_doctor_detail->doctor_profile)->where('doctor_id',$get_doctor_detail->doctor_id)->where('stockiest_id',$stockiest_id)->where('is_considered',0)->sum('previous_sales_amount');

                            //eligibility
                            $new_amount = $amount - $previous_store_sales;

                            if(isset($get_doctor_detail['commission']['commission']) && $get_doctor_detail['commission']['commission'] != ''){

                                if(isset($get_doctor_offset->carry_forward) && $get_doctor_offset->carry_forward != ''){
                                    
                                    $total_sales = $new_amount + $previous_sales + $get_doctor_offset->carry_forward;
                                    $eligibility = $total_sales * $get_doctor_detail['commission']['commission']/100;
                                    
                                }else{
                                    
                                    $total_sales = $new_amount + $previous_sales + 0;
                                    $eligibility =  $total_sales * $get_doctor_detail['commission']['commission']/100;
                                }

                                //carry forward
                                if(isset($get_doctor_offset->target_previous_month) && $get_doctor_offset->target_previous_month != '' && $get_doctor_offset->target_previous_month != 0){
                                    $target = $get_doctor_offset->target_previous_month - $new_amount;
                                }else{
                                    $target = 0;
                                }
                                
                                $carry_forward  = $total_sales - $target;
                                $carry_forward = $carry_forward;
                                
                            }else{
                                
                                if(isset($get_doctor_offset->carry_forward) && $get_doctor_offset->carry_forward != ''){
                                    $eligibility = $new_amount + $previous_sales + $get_doctor_offset->carry_forward * 0/100;
                                }else{
                                    $eligibility = $new_amount + $previous_sales + 0 * 0/100;
                                }
                                //carry forward
                                $carry_forward = $eligibility * 0;
                                $carry_forward = $carry_forward;

                            }
                            
                            $save_offset = new DoctorOffset;
                            if(isset($get_doctor_offset->last_month_sales) && $get_doctor_offset->last_month_sales != ''){
                                $save_offset->last_month_sales = $get_doctor_offset->last_month_sales + $new_amount;
                            }else{
                                $save_offset->last_month_sales = 0  + $new_amount;
                            }

                            //set carry forward and eligibility date
                            $previous_month_date = date('Y-m-d', strtotime('-1 day', strtotime($current_date)));

                            $save_offset->last_month_date = $previous_month_date;
                            if(isset($get_doctor_offset->previous_second_month_sales) && $get_doctor_offset->previous_second_month_sales != ''){
                                $save_offset->previous_second_month_sales = $get_doctor_offset->previous_second_month_sales;
                            }else{
                                $save_offset->previous_second_month_sales = 0;
                            }

                            //set previous and second month date
                            $previous_third_month_date = date('Y-m-d', strtotime('-2 month', strtotime($current_date)));
                            $previous_second_month_date = date('Y-m-d', strtotime('-1 month', strtotime($current_date)));

                            $save_offset->previous_second_month_date = $previous_second_month_date;
                            if(isset($get_doctor_offset->previous_third_month_sales) && $get_doctor_offset->previous_third_month_sales != ''){
                                $save_offset->previous_third_month_sales = $get_doctor_offset->previous_third_month_sales;
                            }else{
                                $save_offset->previous_third_month_sales = 0;
                            }
                            $save_offset->previous_third_month_date = $previous_third_month_date;
                            $carry_forward_date = date('Y-m-d', strtotime('+1 month', strtotime($current_date)));
                            if(isset($get_doctor_offset->target_previous_month) && $get_doctor_offset->target_previous_month != '' &&$get_doctor_offset->target_previous_month != 0){
                                $save_offset->target_previous_month = $get_doctor_offset->target_previous_month - $new_amount;
                            }else{
                                $save_offset->target_previous_month = 0;
                            }
                            $save_offset->target_previous_month_date = $current_date;
                            $save_offset->carry_forward = $carry_forward;
                            $save_offset->carry_forward_date = $current_date;
                            $save_offset->eligible_amount = $eligibility;
                            $save_offset->eligible_amount_date = $current_date;
                            $save_offset->profile_id = $get_doctor_detail->doctor_profile;
                            $save_offset->doctor_id = $get_doctor_detail->doctor_id;
                            $save_offset->save();

                            $get_doctor_sales = MedicalStoreDoctorData::where('doctor_profile',$get_doctor_detail->doctor_profile)->where('doctor_id',$get_doctor_detail->doctor_id)->where('stockiest_id',$stockiest_id)->update(['is_considered' => 1]);
                        }

                    }
                    
                }
                
                //change entry status
                $store_entry_update = StockiestWiseMedicalStoreData::where('id',$data['store_id'])->update(['entry_status' => $data['entry_status']]);

                if($update_amount){

                    $message = 'Amount successfully updated!';
                    return $this->responseSuccess($message);  

                } else {

                    $message = 'Something went wrong!';
                    return $this->responseFailer($message);

                }
                            
            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }
    }

    //update confirm data
    public function updateConfirmData(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if(count($errors_array) == 0){
            $data = Request::all();

            if(isset($data['stockist_id']) && (isset($data['is_completed']))){

                $update_status = MrWiseStockiestData::where('id',$data['stockist_id'])->where('mr_id',$headers['user-id'])->update(['is_completed' => $data['is_completed']]);

                $update_status = StockiestWiseMedicalStoreData::where('stockiest_id',$data['stockist_id'])->where('mr_id',$headers['user-id'])->update(['entry_status' => 2]);
                
            }else if (isset($data['sales_month'])) {
                
                $query = MrWiseStockiestData::query();
                $date = explode('-',$data['sales_month']);
                $query->where('mr_id',$headers['user-id']);
                $query->whereMonth('sales_month', '=', $date[0]);
                $query->whereYear('sales_month', '=', $date[1]);
                $update_status = $query->update(['is_confirm_data' => 1]);
                
                //sales history confirm
                $query = SalesHistory::query();
                $date = explode('-',$data['sales_month']);
                $query->where('mr_id',$headers['user-id']);
                $query->whereMonth('sales_month', '=', $date[0]);
                $query->whereYear('sales_month', '=', $date[1]);
                $update_status = $query->update(['is_submited' => 1,'submitted_on' => date("Y-m-d")]);
                
            }

            if($update_status){

                $message = 'Successfully confirm!';
                return $this->responseSuccess($message);  

            } else {

                $message = 'Something went wrong!';
                return $this->responseFailer($message);

            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }
    }

    public function uploadStatement(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('stockist_id') || Request::get('stockist_id') == "")
            $errors_array['stockist_id'] = 'Please pass stockist id';

        if(count($errors_array) == 0){

            $data = Request::all();
            
            $path = URL::to('/uploads');

            if(isset($data['statement']) && (!empty($data['statement']))){
                foreach ($data['statement'] as $sk => $sv) {
                   
                    
                    $save_statement = new StockiestStatement;
                    $save_statement->data_id = $headers['user-id'];   
                    $statement = $this->uploadImage($sv,'statement');
                    $save_statement->statement = $statement;  
                    $save_statement->save();
                    $save_statement;
                }

                
            }
            
            //get attachement
            $get_attachment = StockiestStatement::where('data_id',$data['stockist_id'])->where('is_delete',0)->paginate(20);

            if(!empty($get_attachment)){
                $documents = array();
                foreach($get_attachment as $ak => $av){

                    $documents[$ak]['id'] = $this->nulltoblank($av->id);
                    $documents[$ak]['document_name'] = $this->nulltoblank($av->statement);
                    $documents[$ak]['document_url'] = $path.'/statement/'.$av->statement;
                    $extension = substr($av->statement, strpos($av->statement, ".") + 1);
                    $documents[$ak]['document_extension'] = $extension;
                    
                }

                if(!empty($documents)){
                    $data['total_documents'] = $get_attachment->total();
                    $data['documents'] = $documents;
                    $message = '';
                    return $this->responseSuccess($message,$data);
                }else{
                    $data['documents'] = [];
                    $message = 'Requests not found!';
                    return $this->responseDatanotFound($message,$data);
                }

            } else {

                /*$message = 'Attachment not available right now!';
                return $this->responseFailer($message);*/

                $message = 'Attachment not available right now!';
                return $this->responseDatanotFound($message);
            }
            
        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }   
    }

    public function removeUploadStatement(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('stockist_id') || Request::get('stockist_id') == "")
            $errors_array['stockist_id'] = 'Please pass stockist id';

        if (!Request::has('statement_id') || Request::get('statement_id') == "")
            $errors_array['statement_id'] = 'Please pass statement id';

        if(count($errors_array) == 0){

            $data = Request::all();

            $remove_statement = StockiestStatement::where('data_id',$data['stockist_id'])->where('id',$data['statement_id'])->update(['is_delete' => 1]);

            if($remove_statement){

                $message = 'Statement deleted!';
                return $this->responseSuccess($message);

            }else{

                $message = 'Something went wrong!';
                return $this->responseFailer($message);

            }

        }else{
            
            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }

    }

    public function mrDoctorList(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if(count($errors_array) == 0){

            $data = Request::all();

            $get_mr_territories = MrDetail::with(['get_territory'])->where('id',$headers['user-id'])->first();

            //mr territories and sub territories
            $territories = array();
            $sub_territories = array();
            if(!empty($get_mr_territories['get_territory'])){
                
                foreach ($get_mr_territories['get_territory'] as $tk => $tv) {

                    $territories[] = $tv['territories_id'];
                    $sub_territories[] = $tv['sub_territories'];
                }
            }

            $all_doctor = DoctorDetail::with(['get_territory'])->where('is_delete',0)->get();
      
            //get stockiest depend on mr territories & sub territories
            $doctor_id = array();
            
            if(!empty($all_doctor)){
                foreach ($all_doctor as $dk => $dv) {
                    foreach ($dv['get_territory'] as $dk => $dv) {
                        if(in_array($dv['territories_id'],$territories) && in_array($dv['sub_territories'],$sub_territories)){
                            $doctor_id[] = $dv->doctor_id;

                        }
                    }
                }
            }

            $query = DoctorProfile::query();
            $query->whereIn('doctor_id',$doctor_id);
            $query->where('is_delete',0);
            $query->with(['doctor_detail']);
            if(isset($data['doctor_name'])){
                $doctor_name = $data['doctor_name'];
                $query->where('profile_name','like', '%' . $doctor_name . '%')->orWhereHas('doctor_detail', function ($query) use ($doctor_name) { $query->where('full_name','like', '%' . $doctor_name . '%'); })->whereIn('doctor_id',$doctor_id);

            }else{
                $query->with(['doctor_detail']);
            }
            $query->orderBy('doctor_id','ASC');
            $get_doctor = $query->paginate(20);
          
            if(!empty($get_doctor)){
                $doctor_data = array();

                foreach ($get_doctor as $gk => $gv) {
                
                    $doctor_data[$gk]['id'] = $this->nulltoblank($gv->id);
                    $doctor_data[$gk]['doctor_id'] = $gv['doctor_detail']['id'];

                    if($gv['profile_name'] != ''){
                        $doctor_data[$gk]['doctor_name'] = $this->nulltoblank($gv['doctor_detail']['full_name'].' ('.$gv->profile_name.')');    
                    }else{
                        $doctor_data[$gk]['doctor_name'] = $this->nulltoblank($gv['doctor_detail']['full_name']);    
                    }                    

                }

                if(!empty($doctor_data)){
                    $data['total_doctor'] = $get_doctor->total();
                    $data['doctors'] = $doctor_data;
                    $message = '';
                    return $this->responseSuccess($message,$data);
                }else{
                    $data['doctors'] = [];
                    $message = 'Requests not found!';
                    return $this->responseDatanotFound($message,$data);
                }


            } else {

                /*$message = 'Doctors not found!';
                return $this->responseFailer($message);*/

                $message = 'Doctors not found!';
                return $this->responseDatanotFound($message);

            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }

    }   

    public function addDoctorRequest(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('profile_id') || Request::get('profile_id') == "")
            $errors_array['profile_id'] = 'Please pass profile id';

        if (!Request::has('doctor_id') || Request::get('doctor_id') == "")
            $errors_array['doctor_id'] = 'Please pass doctor id';

        if (!Request::has('request_date') || Request::get('request_date') == "")
            $errors_array['request_date'] = 'Please pass request date';

        if (!Request::has('eligible_amount') || Request::get('eligible_amount') == "")
            $errors_array['eligible_amount'] = 'Please pass eligible amount';

        if (!Request::has('required_amount') || Request::get('required_amount') == "")
            $errors_array['required_amount'] = 'Please pass required amount';

        if (!Request::has('reason') || Request::get('reason') == "")
            $errors_array['reason'] = 'Please pass reason';

        if(count($errors_array) == 0){
            $data = Request::all();

            //add doctor
            $doctor = new AllRequest;
            $doctor->doctor_id = $data['doctor_id'];
            $doctor->profile_id = $data['profile_id'];
            $doctor->request_date = $this->convertDate($data['request_date']);
            $doctor->eligible_amount = $data['eligible_amount'];
            $doctor->request_amount = $data['required_amount'];
            $doctor->reason = $data['reason'];
            $doctor->submitted_by = $headers['user-id'];
            $doctor->submitted_on = date('Y-m-d');
            $doctor->save();

            $get_doctor_territories = DoctorDetail::with(['get_territory'])->first();
   
            if(!empty($get_doctor_territories['get_territory'])){
                foreach ($get_doctor_territories['get_territory'] as $tk => $tv) {
                    $territories = new DoctorRequestTerritorry;
                    $territories->request_id = $doctor->id;
                    $territories->territory_id = $tv['territories_id'];
                    $territories->sub_territory_id = $tv['sub_territories'];
                    $territories->save();
                }
            }

            if($doctor){

                $message = 'Request successfully Added!';
                return $this->responseSuccess($message);  

            }else{

                $message = 'Something went wrong!';
                return $this->responseFailer($message);
            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }        

    }

    public function doctorRequestList(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('profile_id') || Request::get('profile_id') == "")
            $errors_array['profile_id'] = 'Please pass profile id';

        if (!Request::has('doctor_id') || Request::get('doctor_id') == "")
            $errors_array['doctor_id'] = 'Please pass doctor id';

        if(count($errors_array) == 0){
            $data = Request::all();

            $get_doctor_request = AllRequest::where('profile_id',$data['profile_id'])->where('doctor_id',$data['doctor_id'])->where('submitted_by',$headers['user-id'])->paginate(20);
 
            if(!empty($get_doctor_request)){
                $doctorRequestData = array();

                foreach ($get_doctor_request as $gk => $gv) {
                
                    $doctorRequestData[$gk]['id'] = $this->nulltoblank($gv->id);
                    $doctorRequestData[$gk]['request_date'] = (($gv->request_date != '') ? date('j M,Y',strtotime($gv->request_date)) : ''); 
                    $doctorRequestData[$gk]['request_amount'] = $this->nulltoblank($gv->request_amount);
                    $doctorRequestData[$gk]['reason'] = $this->nulltoblank($gv->reason);
                    
                    $doctorRequestData[$gk]['status'] = ($gv->status == 0) ? 0 : (($gv->status == 1) ? 1 : 2);

                    $doctorRequestData[$gk]['received_by_mr'] = (($gv->received_by_mr == 0) ? 0 : 1);

                    if($gv->received_by_mr == 0){

                        $doctorRequestData[$gk]['paid_to_doctor'] = "";

                        if($gv->is_paid_to_doctor == 0){
                            $doctorRequestData[$gk]['paid_on'] = ""; 
                        }else{
                            $doctorRequestData[$gk]['paid_on'] = ""; 
                        }    

                    }else{

                        $doctorRequestData[$gk]['paid_to_doctor'] = (($gv->is_paid_to_doctor == 0) ? 0 : 1);

                        if($gv->is_paid_to_doctor == 0){
                            $doctorRequestData[$gk]['paid_on'] = (($gv->paid_on != '') ? date('j M,Y',strtotime($gv->paid_on)) : ''); 
                        }else{
                            $doctorRequestData[$gk]['paid_on'] = (($gv->paid_on != '') ? date('j M,Y',strtotime($gv->paid_on)) : ''); 
                        }    

                    }
                    
                }

                $doctor_offset = DoctorOffset::where('profile_id',$data['profile_id'])->where('doctor_id',$data['doctor_id'])->orderBy('id','DESC')->first();

                    //send doctor offsets
                    $data['last_month_sales_heading'] = (!empty($doctor_offset) ? 'Sales of '.date('M y', strtotime($doctor_offset->last_month_date)) : ''); 
                    $data['last_month_sales'] = (!empty($doctor_offset) ? '₹ '.$doctor_offset->last_month_sales : '₹  0'); 

                    $data['second_month_heading'] = (!empty($doctor_offset) ? 'Sales of '.date('M y', strtotime($doctor_offset->previous_second_month_date)) : '');
                    $data['second_month_sales'] = (!empty($doctor_offset) ? '₹ '.$doctor_offset->previous_second_month_sales : '₹  0'); 

                    $data['third_month_heading'] = (!empty($doctor_offset) ? 'Sales of '.date('M y', strtotime($doctor_offset->previous_third_month_date)) : ''); 
                    $data['third_month_sales'] = (!empty($doctor_offset) ? '₹ '.$doctor_offset->previous_third_month_sales : '₹  0'); 

                    $data['target_sales_heading'] = (!empty($doctor_offset) ? 'Target Sales ' : ''); 
                    $data['target_sales'] = (!empty($doctor_offset) ? '₹ '.$doctor_offset->target_previous_month: '₹  0'); 

                    $data['carry_forward_heading'] = (!empty($doctor_offset) ? 'Carry Forward Amount' : ''); 
                    $data['carry_forward_sales'] = (!empty($doctor_offset) ? '₹ '.$doctor_offset->carry_forward: '₹  0'); 

                    $data['eligible_heading'] = (!empty($doctor_offset) ? 'Eligible Amount'  : ''); 
                    $data['eligible_sales'] = (!empty($doctor_offset) ? '₹ '.$doctor_offset->eligible_amount: '₹  0'); 

                if(!empty($doctorRequestData)){
                    $data['total_stockist'] = $get_doctor_request->total();
                    $data['doctor_request'] = $doctorRequestData;
                    $message = '';
                    return $this->responseSuccess($message,$data);
                }else{
                    $data['doctor_request'] = [];
                    $message = 'Requests not found!';
                    return $this->responseDatanotFound($message,$data);
                }
                
                return $this->responseSuccess($message,$data);

            } else {

                /*$message = 'Request not found!';
                return $this->responseFailer($message);*/

                $message = 'Requests not found!';
                return $this->responseDatanotFound($message);

            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }

    }

    //all request list
    public function allDoctorRequestList(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if(count($errors_array) == 0){
            $data = Request::all();

            $query = AllRequest::query();
            $query->with(['doctor_detail','profile_detail']);
            $query->where('submitted_by',$headers['user-id']);      
            if(isset($data['doctor_name']) && $data['doctor_name'] != ''){

                $doctor_name = $data['doctor_name'];
                $query->whereHas('doctor_detail', function ($query) use ($doctor_name) { $query->where('full_name', 'like','%' . $doctor_name . '%'); })->orWhereHas('profile_detail', function ($query) use ($doctor_name) { $query->where('profile_name', 'like','%' . $doctor_name . '%'); });
                

            } else {

                $query->with(['doctor_detail','profile_detail']);
            }
            $get_doctor_data = $query->paginate(20);


            if(!empty($get_doctor_data)){
                $doctorRequestData = array();

                foreach ($get_doctor_data as $gk => $gv) {
                
                    $doctorRequestData[$gk]['id'] = $this->nulltoblank($gv->id);
                    if($gv['profile_detail']['profile_name'] != ''){
                        $doctorRequestData[$gk]['doctor_name'] = $gv['doctor_detail']['full_name'].' ( '.$gv['profile_detail']['profile_name'].' )';    
                    }else{
                        $doctorRequestData[$gk]['doctor_name'] = $gv['doctor_detail']['full_name'];    
                    }
                    
                    $doctorRequestData[$gk]['request_date'] = (($gv->request_date != '') ? date('j M,Y',strtotime($gv->request_date)) : ''); 
                    $doctorRequestData[$gk]['request_amount'] = $this->nulltoblank($gv->request_amount);
                    $doctorRequestData[$gk]['reason'] = $this->nulltoblank($gv->reason);
                    $doctorRequestData[$gk]['status'] = ($gv->status == 0) ? 0 : (($gv->status == 1) ? 1 : 2);
                    $doctorRequestData[$gk]['is_payment_genrated'] = (($gv->is_payment_genrated == 0) ? 0 : 1);

                    if($gv->is_payment_genrated == 0){

                        $doctorRequestData[$gk]['recived_on'] = ''; 

                        $doctorRequestData[$gk]['paid_on'] = ''; 
                        

                    }else{

                        $doctorRequestData[$gk]['paid_to_doctor'] = (($gv->is_paid_to_doctor == 0) ? 0 : 1);
                        $doctorRequestData[$gk]['recived_on'] = (($gv->received_on != '') ? date('j M,Y',strtotime($gv->received_on)) : ''); 
                        if($gv->is_paid_to_doctor == 0){
                            $doctorRequestData[$gk]['paid_on'] = (($gv->paid_on != '') ? date('j M,Y',strtotime($gv->paid_on)) : ''); 
                        }else{
                            $doctorRequestData[$gk]['paid_on'] = (($gv->paid_on != '') ? date('j M,Y',strtotime($gv->paid_on)) : ''); 
                        }    

                    }

                    
                }
                if(!empty($doctorRequestData)){
                    $data['total_stockist'] = $get_doctor_data->total();
                    $data['doctor_request'] = $doctorRequestData;
                    $message = '';
                    return $this->responseSuccess($message,$data);
                }else{
                    $data['doctor_request'] = [];
                    $message = 'Requests not found!';
                    return $this->responseDatanotFound($message,$data);
                }
                
            } else {

                /*$message = 'Record not found!';
                return $this->responseFailer($message);*/

                $message = 'Requests not found!';
                return $this->responseDatanotFound($message);

            }

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }

    }

    public function updateDoctorPayment(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('request_id') || Request::get('request_id') == "")
            $errors_array['request_id'] = 'Please pass request id';


        if(count($errors_array) == 0){
            $data = Request::all();

            $current_date = date("Y-m-d");
            if(isset($data['paid_to_doctor']) && ($data['paid_to_doctor']  != '')){

                $update_request = AllRequest::where('id',$data['request_id'])->update(['is_paid_to_doctor' => $data['paid_to_doctor'],'paid_on' => $current_date]);    
            }else{
                
                if(isset($data['received']) && ($data['received']  != '')){

                    $update_request = AllRequest::where('id',$data['request_id'])->update(['received_by_mr' => $data['received'],'received_on' => $current_date]);    

                }                
            }

            if(isset($update_request)){

                $message = 'Payment status successfully updated!';
                $current_date = date("j M,Y");
                $data = $current_date;
                return $this->responseSuccess($message,$data);  

            }else{

                $message = 'Pass all parameter!';
                return $this->responseFailer($message);

            }           

        } else {

            $errors = $errors_array;
            $message = 'errors';
            return $this->responseFailer($message,$errors);

        }
    }

    //calendar function
    public function calendarDetail(){

        $this->LogInput();
        $errors_array = array();
        $headers = apache_request_headers();

        if (!isset($headers['user-id']) || $headers['user-id'] == "")
            $errors_array['user-id'] = 'Please pass user id';

        if (!Request::has('date') || Request::get('date') == "")
            $errors_array['date'] = 'Please pass date';

            if(count($errors_array) == 0){
                $data = Request::all();

                $date = explode('/',$data['date']);
                
                $year = $date[01];

                $get_territories = MrTerritory::where('mr_id',$headers['user-id'])->where('territories_id','!=','')->pluck('territories_id');
                $get_sub_territories = MrTerritory::where('mr_id',$headers['user-id'])->where('sub_territories','!=','')->pluck('sub_territories');

                $doctors_dob = DoctorDetail::where('is_delete', 0)->selectRaw('year(dob) year, month(dob) month, day(dob) date, full_name, id')->groupBy('id', 'year', 'month', 'date', 'full_name')->whereHas('territory_detail', function ($query) use ($get_territories) { $query->whereIn('territories_id', $get_territories); })->WhereHas('territory_detail', function ($query) use ($get_sub_territories) { $query->whereIn('sub_territories',$get_sub_territories); })->with(['territory_detail' => function($q){ $q->with(['territory_name']); }])->whereRaw('extract(month from dob) = ?', [$date[0]])->get();
                
                // Doctors anniversary date
                $doctors_anniversary = DoctorDetail::where('is_delete', 0)->selectRaw('year(anniversary_date) year, month(anniversary_date) month, day(anniversary_date) date, full_name, id')->groupBy('id', 'year', 'month', 'date', 'full_name')->whereHas('territory_detail', function ($query) use ($get_territories) { $query->whereIn('territories_id', $get_territories); })->WhereHas('territory_detail', function ($query) use ($get_sub_territories) { $query->whereIn('sub_territories',$get_sub_territories); })->with(['territory_detail' => function($q){ $q->with(['territory_name']); }])->whereRaw('extract(month from anniversary_date) = ?', [$date[0]])->get();
                
                // Doctors clinic opening date
                $clinic_open_date = DoctorDetail::where('is_delete', 0)->selectRaw('year(clinic_opening_date) year, month(clinic_opening_date) month, day(clinic_opening_date) date, full_name, id')->groupBy('id', 'year', 'month', 'date', 'full_name')->whereHas('territory_detail', function ($query) use ($get_territories) { $query->whereIn('territories_id', $get_territories); })->WhereHas('territory_detail', function ($query) use ($get_sub_territories) { $query->whereIn('sub_territories',$get_sub_territories); })->with(['territory_detail' => function($q){ $q->with(['territory_name']); }])->whereRaw('extract(month from clinic_opening_date) = ?', [$date[0]])->get();
                
                // Mr payment received date
                $payment_rec = AllRequest::with(['mr_detail'])->whereHas('main_territory_detail', function ($query) use ($get_territories) { $query->whereIn('territory_id', $get_territories); })->WhereHas('main_territory_detail', function ($query) use ($get_sub_territories) { $query->whereIn('sub_territory_id',$get_sub_territories); })->whereMonth('received_on','=', $date[0])->whereYear('received_on','=',$date[01])->get();

                // Mr payment paid to doctor date
                $payment_paid = AllRequest::with(['mr_detail'])->with(['doctor_detail'])->whereHas('main_territory_detail', function ($query) use ($get_territories) { $query->whereIn('territory_id', $get_territories); })->WhereHas('main_territory_detail', function ($query) use ($get_sub_territories) { $query->whereIn('sub_territory_id',$get_sub_territories); })->whereMonth('paid_on','=', $date[0])->whereYear('paid_on', '=',$date[01])->get();
             
                $dob = array();        
                $anniversary = array();
                $clinic_open = array();
                $pay_rec = array();    
                $pay_paid = array();   

                if(!empty($doctors_dob)){
                    $birthday_date = [];
                    foreach ($doctors_dob as $bk => $bv) {
       
                        $date = $bv->month."/".$bv->date;
                        if(!in_array($date,$birthday_date)){
                            $birthday_date[] = $date;
                            $dob[$bk]['name'] = "Birthday";
                        }else{
                            $dob[$bk]['name'] = '';
                        }
                        if(!empty($bv->territory_detail)){
                            $dob[$bk]['event_description'] = $bv->full_name." (". $bv['territory_detail'][0]['territory_name']['territory_id'].")"; 
                        }else{
                            $dob[$bk]['event_description'] = $bv->full_name;  
                        }
                        
                        $date2 = strtotime($year."-".$bv->month."-".$bv->date);
                        $dob[$bk]['date'] = date('Y-m-d', $date2);
                        $dob[$bk]['type'] = "birthday";

                    }
                }

                if(!empty($doctors_anniversary)){
                    $anniversary_date = [];
                    foreach ($doctors_anniversary as $ak => $av) {

                        $date = $av->month."/".$av->date;
                        if(!in_array($date,$anniversary_date)){
                            $anniversary_date[] = $date;
                            $anniversary[$ak]['name'] = "Anniversary";
                        }else{
                            $anniversary[$ak]['name'] = '';
                        }

                        if(!empty($av->territory_detail)){
                            $anniversary[$ak]['event_description'] = $av->full_name." (". $av['territory_detail'][0]['territory_name']['territory_id'].")";
                        }else{
                            $anniversary[$ak]['event_description'] = $av->full_name;
                        }
                        
                        $date2 = strtotime($av->month."/".$av->date."/".$year);
                        $anniversary[$ak]['date'] = date('Y-m-d', $date2);
                        $anniversary[$ak]['type'] = "anniversary";

                    }
                }

                if(!empty($clinic_open_date)){
                    $opening_date = [];
                    foreach ($clinic_open_date as $ck => $cv) {
                        
                        $date = $cv->month."/".$cv->date;
                        if(!in_array($date,$opening_date)){
                            $opening_date[] = $date;
                            $clinic_open[$ck]['name'] = "Clinic opening anniversary";
                        }else{
                            $clinic_open[$ck]['name'] = '';
                        }

                        if(!empty($cv->territory_detail)){
                            
                            $clinic_open[$ck]['event_description'] = $cv->full_name." (". $cv['territory_detail'][0]['territory_name']['territory_id'].")";
                        }else{
                            
                            $clinic_open[$ck]['event_description'] = $cv->full_name;
                        }
                        $date2 = strtotime($cv->month."/".$cv->date."/".$year);
                        $clinic_open[$ck]['date'] = date('Y-m-d', $date2);
                        $clinic_open[$ck]['type'] = "clinic_opening";
                        
                    }
                }

                if(!empty($payment_rec)){
                    $recived_date = [];
                    foreach ($payment_rec as $rk => $rv){
                        
                        $date = $rv->month."/".$rv->date;
                        if(!in_array($date,$recived_date)){
                            $recived_date[] = $date;
                            $pay_rec[$rk]['name'] = "MR payment received";
                        }else{
                            $pay_rec[$rk]['name'] = '';
                        }
                        $pay_rec[$rk]['event_description'] = "₹ ".$rv->request_amount." received by ".$rv->mr_detail->full_name;  
                        
                        $pay_rec[$rk]['date'] = $rv->received_on;
                        $pay_rec[$rk]['type'] = "mr_receive";

                    }
                }

                if(!empty($payment_paid)){
                    $paid_date = [];
                    foreach ($payment_paid as $pk => $pv) {

                        $date = $pv->month."/".$pv->date;
                        if(!in_array($date,$paid_date)){
                            $paid_date[] = $date;
                            $pay_paid[$pk]['name'] = 'MR payment paid to doctor';
                        }else{
                            $pay_paid[$pk]['name'] = '';
                        }
                        $pay_paid[$pk]['event_description'] = $pv->mr_detail->full_name." paid ₹ ".$pv->request_amount." to ".$pv->doctor_detail->full_name;  
                        $pay_paid[$pk]['date'] = $rv->paid_on;
                        $pay_paid[$pk]['type'] = "mr_paid_to_doctor";
                    }
                }

                $all_data = array_merge($dob,$anniversary,$clinic_open,$pay_rec,$pay_paid);
                
                if(!empty($all_data)){

                    $data['calendar_data'] = $all_data;
                    $message = '';
                    return $this->responseSuccess($message,$data);    

                }else{

                    $message = 'Events not found!';
                    return $this->responseDatanotFound($message);

                }
                

            }else{

                $errors = $errors_array;
                $message = 'errors';
                return $this->responseFailer($message,$errors);

            }

    }
}   

?>