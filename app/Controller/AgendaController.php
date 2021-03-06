<?php

namespace App\Controller;

use JPF\Config\Config;
use App\Model\AgendaModel;
use \Firebase\JWT\JWT;
use Carbon\Carbon;
use Exception;
use Rakit\Validation\Validator;


class AgendaController
{
    private $jwt_secret_key;
    private $model;

    public function __construct()
    {
        $this->model = new AgendaModel();
        $this->jwt_secret_key = Config::get('JWT_SECRET');
    }

    private function getBearerTokenData($bearer) 
    {
        $decoded = JWT::decode($bearer, $this->jwt_secret_key, array('HS256'));
        return $decoded;
    }

    public function getUserContacts($page, $perpage, $token) 
    {
        //Use token to get only data from this user
        $info = $this->getBearerTokenData($token);

        //Pagination set by json in body
        if(isset($page) && isset($perpage)){
            $execution['contacts'] = $this->model->getPaginatedByUser($page, $perpage, $info->user_id);

            $paginator_check = $this->model->paginationCheck($info->user_id);

            if($paginator_check !== FALSE)
            {
                $total_contacts = $paginator_check[0]['COUNT(*)'];

                $total_pages = ceil($total_contacts/ $perpage);
                $next_page = (($page + 1) > $total_pages) ? -1 : ($page + 1);
                $prev_page = (($page - 1) < 1) ? -1 : ($page - 1);
    
                $execution['pagination'] = [
                    'page' => $page, 
                    'perpage' => $perpage, 
                    'next_page' => $next_page, 
                    'prev_page' => $prev_page, 
                    'total_pages' => $total_pages
                ];
            }
        } else {
            $execution = $this->model->getAllByUser($info->user_id);
        } 

        if($execution !== FALSE)
            return ['message' => 'Success', 'data' => $execution];
        else 
            return ['code' => 500, 'message' => 'Failed to find user contacts'];
    }

    public function getUserContactsByID($id, $token) 
    {
        //Use token to get only data from this user
        $info = $this->getBearerTokenData($token);

        //Token to validate if user has access to this contact
        $execution = $this->model->getContactsByID($id, $info->user_id);
        if($execution !== FALSE)
            return ['message' => 'Success', 'data' => $execution];
        else 
            return ['code' => 500, 'message' => 'Failed to find user contact by id'];
    }

    public function insert($data, $token) 
    {
        //Use token to get only data from this user
        $info = $this->getBearerTokenData($token);

        $validator = new Validator;

        $validation = $validator->make((array) $data, [
            'first_name'            => 'required|min:5|max:40',
            'last_name'             => 'required|alpha_spaces',
            'email'                 => 'required|email|min:8|max:60',
            'address_city'          => 'alpha_spaces|min:5|max:40',
            'address_state'         => 'alpha_spaces|min:5|max:40',
            'address'               => 'alpha_spaces|min:5|max:40',
            'address_number'        => 'integer',
            'address_cep'           => 'alpha_dash|min:5|max:20',
            'address_district'      => 'alpha_spaces|min:5|max:40',
            'phones'                => 'array',
            'phones.*'              => 'required'
        ]);
        
        $validation->validate();
        
        if ($validation->fails()) {
            // handling errors
            $errors = $validation->errors();
            return ['code' => 400, 'message' =>  $errors->firstOfAll()];
        } 

        $execution_contact = $this->model->insertUserContact($data, $info->user_id);

        if($execution_contact !== FALSE) {
            foreach($data->phones as $phone) {
                $execution_phone = $this->model->insertContactPhone($phone->number, $execution_contact);

                if($execution_phone === FALSE)
                    return ['code' => 500, 'message' => 'Failed to register phone'];
            } 
            return ['message' => 'Success'];
        }
        else 
            return ['code' => 500, 'message' => 'Failed to register'];


    }

    public function update($data, $token) 
    {
        //Use token to verify if this contact belongs to the user
        $info = $this->getBearerTokenData($token);
                
        $validator = new Validator;

        $validation = $validator->make((array) $data, [
            'id'                    => 'required',
            'first_name'            => 'min:5|max:40',
            'last_name'             => 'alpha_spaces',
            'email'                 => 'email|min:8|max:60',
            'address_city'          => 'alpha_spaces',
            'address_state'         => 'alpha_spaces',
            'address'               => 'alpha_spaces',
            'address_number'        => 'integer',
            'address_cep'           => 'alpha',
            'address_district'      => 'alpha_spaces',
        ]);
        
        $validation->validate();
        
        if ($validation->fails()) {
            // handling errors
            $errors = $validation->errors();
            return ['code' => 400, 'message' =>  $errors->firstOfAll()];
        } 

        $execution_contact = $this->model->updateUserContact($data, $info->user_id);

        foreach($data->phones as $phone) {
            $execution_phone = $this->model->updateContactPhone($phone->id, $phone->number);
        } 
        return ['message' => $execution_contact];
    }

    public function delete($id, $token) 
    {
        //Use token to verify if this contact belongs to the user
        $info = $this->getBearerTokenData($token);

        $execution = $this->model->deleteContact($id, $info->user_id);

        if($execution !== FALSE)
            return ['message' => 'Success'];
        else 
            return ['code' => 500, 'message' => 'Failed to find user contacts'];
    }
}