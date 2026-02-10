<?php

namespace App\Http\Controllers;

use Auth;
use App\Model\MrDetail;
use App\Model\Territory;
use App\Model\Stockiest;
use App\Model\AllRequest;
use App\Model\DoctorOffset;
use App\Model\DoctorDetail;
use App\Model\UserTerritory;
use App\Model\SubTerritory;
use App\Model\SalesHistory;
use App\Model\MrTerritory;
use Illuminate\Http\Request;
use App\Model\AssociatedUser;
use App\Model\MrWiseStockiestData;
use App\Http\Controllers\GlobalController;

class HomeController extends GlobalController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth',['except' => ['monthlySales']]);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {   
        return redirect(route('dashboard'));
    }

    public function getSubTerritories(Request $request){

        $get_sub_territories = SubTerritory::whereIn('territory_id',$request->territories)->where('is_active',1)->where('is_delete',0)->orderBy('territory_id','ASC')->get();

        $option = '<option value="">Select Sub Territory</option>';
        
        if(count($get_sub_territories) > 0){
            foreach($get_sub_territories as $gk => $gv){
                $option .= "<option value='".$gv->id."'>".$gv->sub_territory."</option>";
                $sub_territory_id[] = $gv->id;
            }
        }
       
        return $option;
    }

    //get user suggestion
    public function getAssociatedUserSuggestion(Request $request){

        $value = json_decode($request->value);  

        $query = AssociatedUser::query();
        $query->where('name','LIKE','%'.$request->name.'%');
        if(!is_null($value)){
            $query->whereNotIn('id',$value);
        }
        $companyContact = $query->get();

        $contactJson = array();

        if(!is_null($companyContact)){
            foreach($companyContact as $pk => $pv){
                $contactJson[$pk]['label'] = $pv->name." (".$pv->mobile.")";
                $contactJson[$pk]['value'] = $pv->id;
            }
        }

        return $contactJson; 

    }

    //save user data
    public function saveUserContact(Request $request){

        
        if($request->user_type == 1){

            $user_save = new AssociatedUser;
            $user_save->name = $request->name;
            $user_save->email = $request->email;
            $user_save->mobile = $request->mobile;
            $user_save->medical_associate = 1;
            $user_save->save();

        }elseif($request->user_type == 2){

            $user_save = new AssociatedUser;
            $user_save->name = $request->name;
            $user_save->email = $request->email;
            $user_save->mobile = $request->mobile;
            $user_save->stockiest_associate = 1;
            $user_save->save();

        }

        $id = $user_save->id;
        $value[] = $id;

        $id = $user_save->id;
        $value[] = $id;

        $html = '';
        $html .= '<tr class="user_dynamic_row">';
        $html .= '<td><center><input type="hidden" name="data['.$id.'][name]" value="'.$request->name.'">'.$request->name.'</center></td>';
        $html .= '<td><center><input type="hidden" name="data['.$id.'][email]" value="'.$request->email.'">'.$request->email.'</center></td>';
        $html .= '<td><center><input type="hidden" name="data['.$id.'][mobile]" value="'.$request->mobile.'">'.$request->mobile.'</center></td>';
        $html .= '<td style="display:none;"><input type="hidden" name="data['.$id.'][id]" value="'.$id.'"></td>';
        $html .= '<td><center><select name="data['.$id.'][engagement_type]" class="form-control" style="width: 208px;" data-msg="Plese Select Engagement Type" required><option value="">Select Engagement Type</option><option value="1">Primary</option><option value="2">Secondary</option></select</center></td>';
        $html .= '<td><center><select name="data['.$id.'][role]" class="form-control" style="width: 160px;" data-msg="Plese Select Role" required><option value="">Select Role</option><option value="1">Owner</option><option value="2">Employee</option></select</center></td>';
        $html .= '<td><a href="javascript:void(0);" class="btn btn-danger deleteThisRow cancel_button" data-id="'.$id.'"><i class="bx bx-trash-alt"></i></a></td>';
        $html .= '</tr>';

        $data['count'] = json_encode($value);
        $data['html'] = $html;

        return $data;
    }

    //get associated Row
    public function getAssociatedUserRow(Request $request){

        $value = json_decode($request->value);

        $query = AssociatedUser::query();
        $query->where('id',$request->id);
        $findUser = $query->first();

        $id = $findUser->id;
        $value[] = $id;

        $html = '';
        $html .= '<tr class="user_dynamic_row">';
        $html .= '<td><center><input type="hidden" name="data['.$id.'][name]" value="'.$request->name.'">'.$findUser->name.'</center></td>';
        $html .= '<td><center><input type="hidden" name="data['.$id.'][email]" value="'.$request->email.'">'.$findUser->email.'</center></td>';
        $html .= '<td><center><input type="hidden" name="data['.$id.'][mobile]" value="'.$request->mobile.'">'.$findUser->mobile.'</center></td>';
        $html .= '<td style="display:none;"><input type="hidden" name="data['.$id.'][id]" value="'.$id.'"></td>';
        $html .= '<td><center><select name="data['.$id.'][engagement_type]" class="form-control" style="width: 208px;" data-msg="Plese Select Engagement Type" required><option value="">Select Engagement Type</option><option value="1">Primary</option><option value="2">Secondary</option></select</center></td>';
        $html .= '<td><center><select name="data['.$id.'][role]" class="form-control" style="width: 160px;" data-msg="Plese Select Role" required><option value="">Select Role</option><option value="1">Owner</option><option value="2">Employee</option></select</center></td>';
        $html .= '<td><a href="javascript:void(0);" class="btn btn-danger deleteThisRow cancel_button" data-id="'.$id.'"><i class="bx bx-trash-alt"></i></a></td>';
        $html .= '</tr>';

        $data['count'] = json_encode($value);
        $data['html'] = $html;

        return $data;

    }

    //check mail id exists or not
    public function checkEmailId(Request $request){

        $query = AssociatedUser::query();
        $query->where('email',$request->email);
        if (isset($request->id)) {
            $query->where('id','!=',$request->id);
        }
            
        $email_id = $query->first();
        
        return (!is_null($email_id) ? 'false' : 'true');        
    }

    public function checkAssociatedUserNumber(Request $request){

        $query = AssociatedUser::query();
        $query->where('mobile',$request->mobile);
        if (isset($request->id)) {
            $query->where('id','!=',$request->id);
        }
        $query->where('is_delete',0);
        $mobile_number = $query->first();
        
        return (!is_null($mobile_number) ? 'false' : 'true');       
           
    }
    
    //all request auto suggestions
    public function getDoctorSuggestion(Request $request){

        $query = DoctorDetail::query();
        $query->where('full_name','LIKE','%'.$request->name.'%');
        $doctor_detail = $query->where('is_delete',0)->get();

        $doctorJson = array();

        if(!is_null($doctor_detail)){
            foreach($doctor_detail as $dk => $dv){
                $doctorJson[$dk]['label'] = $dv->full_name;
                $doctorJson[$dk]['value'] = $dv->id;
            }
        }

        return $doctorJson; 

    }

    //get mr suggestion
    public function getMrSuggestion(Request $request){

        $query = MrDetail::query();
        $query->where('full_name','LIKE','%'.$request->name.'%');
        $mr_detail = $query->where('is_delete',0)->get();

        $mrJson = array();

        if(!is_null($mr_detail)){
            foreach($mr_detail as $mk => $mv){
                $mrJson[$mk]['label'] = $mv->full_name;
                $mrJson[$mk]['value'] = $mv->id;
            }
        }

        return $mrJson; 
    }

    //get territory suggestion
    public function getTerritorySuggestion(Request $request){

        $query = Territory::query();
        $query->where('territory_id','LIKE','%'.$request->name.'%');
        $territory_detail = $query->where('is_delete',0)->get();

        $territoryJson = array();

        if(!is_null($territory_detail)){
            foreach($territory_detail as $tk => $tv){
                $territoryJson[$tk]['label'] = $tv->territory_id;
                $territoryJson[$tk]['value'] = $tv->id;
            }
        }

        return $territoryJson;    
    }

    //get dependent sub territory
    public function getDependentSubTerritories(Request $request){

        //get all sub Territories
        $get_territory = SubTerritory::where('territory_id',$request->territorry_id)->where('is_delete',0)->get();

        if(!$get_territory->isEmpty()){

            $option = '<option value="">Select Sub Territory</option>';

            if(!$get_territory->isEmpty()){
                foreach($get_territory as $tk => $tv){
                    $option .= "<option value='".$tv->id."'>".$tv->sub_territory."</option>";
                }
            }    
        }else{

            $option = '<option value="">Sub Territory Not Found</option>';
        }
        
        return $option;
    }

    public function monthlySales(){
        
        $mr_data = MrDetail::with(['get_territory' => function($q){ $q->with(['territory_name']); }])->where('is_delete',0)->get();

        //mr territories and sub territories
        $territories = array();
        $sub_territories = array();
        if(!empty($mr_data)){
            
            foreach ($mr_data as $mk => $mv) {
                foreach ($mv['get_territory'] as $tk => $tv) {
         
                    $territories[] = $tv['territories_id'];
                    $sub_territories[] = $tv['sub_territories'];

                }
            }
        }
    
        //at 25th date
        $entry_date = date("Y-m");
        $entry_date = $entry_date.'-25';

        //get current date
        $current_date = date("Y-m-d");
        
        //current month sales data
        $get_current_sales_data = SalesHistory::where('sales_month',$current_date)->get();
        

        $get_stockiest = Stockiest::with(['stockiest_territories'])->where('is_delete',0)->get();

        //get stockiest depend on mr territories & sub territories
        $stockies_id = array();
        if(!empty($get_stockiest)){
            foreach ($get_stockiest as $sk => $sv) {
                foreach ($sv['stockiest_territories'] as $tk => $tv) {
                    if(in_array($tv['territories_id'],$territories) && in_array($tv['sub_territories'],$sub_territories)){
                        $stockies_id[] = $sv->id;
                    }
                }
            }
        }
        $stockies_id = array_unique($stockies_id);

        $territorist_stockiest = Stockiest::whereIn('id',$stockies_id)->with(['stockiest_user','stockiest_territories'])->where('is_delete',0)->get();

        if($get_current_sales_data->isEmpty()){
            
            if($entry_date == $current_date){

                //entry of all mr at 20th
                if(!empty($mr_data)){
                    foreach ($mr_data as $mk => $mv) {
           
                        $save_sales_data = new SalesHistory;
                        $save_sales_data->mr_id = $mv->id;
                        $save_sales_data->sales_month = $current_date;
                        $save_sales_data->save();

                        $check_data = MrWiseStockiestData::where('sales_id',$save_sales_data->id)->where('sales_month',$current_date)->first();

                        //save stockiest data
                        $stockiest = [];
                        if(empty($check_data)){
                            if(!empty($territorist_stockiest)){

                                foreach ($territorist_stockiest as $sk => $sv) {
                                    
                                    foreach ($sv['stockiest_territories'] as $tk => $tv) {
                                        $get_territory = MrTerritory::where('mr_id',$save_sales_data->mr_id)->pluck('territories_id')->toArray();
                                        $sub_territories = MrTerritory::where('mr_id',$save_sales_data->mr_id)->pluck('sub_territories')->toArray();

                                        if(in_array($tv['territories_id'],$get_territory) && in_array($tv['sub_territories'],$sub_territories)){
                                            if(!in_array($sv->id,$stockiest)){
                                                $stockiest[] = $sv->id;
                                                $save_stockiest = new MrWiseStockiestData;
                                                $save_stockiest->stockiest_id = $sv->id;
                                                $save_stockiest->sales_id = $save_sales_data->id;
                                                $save_stockiest->mr_id = $save_sales_data->mr_id;
                                                $save_stockiest->sales_month = $current_date;
                                                $save_stockiest->priority = 0;
                                                $save_stockiest->save();    
                                            }
                                        
                                        }                                        

                                    }

                                }
                                    
                            }
                                
                        }

                    }

                }

            }
            
        }
        
        //card value shift and target change by 0
        $get_last_offset = DoctorOffset::whereRaw('extract(month from last_month_date) = ?', [date('m')])->whereRaw('extract(year from last_month_date) = ?', [date('Y')])->get(); 
        
        $current_date = date('Y-m-d');
        if(!empty($get_last_offset)){
            foreach($get_last_offset as $ok => $ov){
              
                $update_offset = DoctorOffset::findOrFail($ov->id);
                $update_offset->last_month_sales = 0;
                $current_date = date('Y-m-d', strtotime('+1 month', strtotime($current_date)));
                $update_offset->last_month_date = $current_date;
                $update_offset->previous_second_month_sales = $ov->last_month_sales;
                $previous_second_month_date = date('Y-m-d', strtotime('-1 month', strtotime($current_date)));
                $update_offset->previous_second_month_date = $previous_second_month_date;
                $update_offset->previous_third_month_sales = $ov->previous_second_month_sales;
                $previous_third_month_date = date('Y-m-d', strtotime('-2 month', strtotime($current_date)));
                $update_offset->previous_third_month_date = $previous_third_month_date;
                $update_offset->target_previous_month = 0;
                $update_offset->target_previous_month_date = $current_date;
                $update_offset->carry_forward_date = $current_date;
                $update_offset->eligible_amount_date = $current_date;
                $update_offset->save();

            }
        }
      

    }
}
