<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Role;
use App\Usuario;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth as JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests;

class UsuarioController extends Controller
{
       protected $usuarioLogado;


    public function __construct()
    {    
        
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function total()
    {
        $total = Usuario::all()->count();
        return $this->response(200, "MSG000151", $total);
    }

    public function pesquisar(Request $request)
    {
        $qtdRegistros = is_null($request->qtdRegistros) || $request->qtdRegistros == -1 ? 999999999999999 : $request->qtdRegistros;
        $pageCurrent = $request->get('pageCurrent');
        $orderBy = is_null($request->orderBy) ? 'id' : $request->orderBy;
        $orderType = is_null($request->orderType) ? 'desc' : $request->orderType;

        $query = Usuario::query();

        //e-mail
        if (!is_null($request->get('email'))) {
            $query = $query->where('usuarios.email', 'like', '%' . $request->get('email') . '%');
        }

        //nome
        if (!is_null($request->get('nome'))) {
            $query = $query->where('usuarios.nome', 'like', '%' . $request->get('nome') . '%');
        }

        //id
        if (!is_null($request->get('id'))) {
            $query = $query->where('usuarios.id', '=', $request->get('id'));
        }

        //confirmacaoEmail
        if (!is_null($request->get('confirmacaoEmail'))) {
            $query = $query->where('confirmacaoEmail', (boolean)json_decode(strtolower($request->get('confirmacaoEmail'))));
        }

        //status
        if (!is_null($request->get('status'))) {
            $query = $query->where('status', '=',$request->get('status'));
        }

        //sexo
        if (!is_null($request->get('sexo'))) {
            $query = $query->where('sexo', '=',$request->get('sexo'));
        }

        //dataCriacao
        $dataCriacaoInicial = $request->get('dataCriacaoInicial');
        $dataCriacaoFinal = $request->get('dataCriacaoFinal');

        if (!is_null(($dataCriacaoInicial)) || !is_null(($dataCriacaoFinal))) {

            if (!is_null(($dataCriacaoInicial))) {
                $inicial = Carbon::parse($dataCriacaoInicial);
                $query = $query->where('dataCriacaoInicial', ">=", $inicial);
            }

            if (!is_null(($dataCriacaoFinal))) {

                $final = Carbon::parse($dataCriacaoFinal)->endOfDay();
                $query = $query->where('dataCriacaoInicial', "<=", $final);
            }
        }

        //dataCriacao
        $dataAtualizacaoInicial = $request->get('dataAtualizacaoInicial');
        $dataAtualizacaoFinal = $request->get('dataAtualizacaoFinal');

        if (!is_null(($dataAtualizacaoInicial)) || !is_null(($dataAtualizacaoFinal))) {

            if (!is_null(($dataAtualizacaoInicial))) {
                $inicial = Carbon::parse($dataAtualizacaoInicial);
                $query = $query->where('dataAtualizacaoFinal', ">=", $inicial);
            }

            if (!is_null(($dataAtualizacaoFinal))) {

                $final = Carbon::parse($dataAtualizacaoFinal)->endOfDay();
                $query = $query->where('dataAtualizacaoFinal', "<=", $final);
            }
        }

        $totalRecordsFilter = $query->get()->Count();

        $records = $query
            ->orderBy($orderBy, $orderType)
            ->select('usuarios.*')
            ->skip($pageCurrent)
            ->take($qtdRegistros)
            ->get();

        $data_arr = array();
        foreach ($records as $record) {
            $id = $record->id;
            $nome = $record->nome;
            $email = $record->email;
            $sexo = $record->sexo;
            $status = $record->status;
            $confirmacaoEmail = $record->confirmacaoEmail;
            $dataCriacao = $record->dataCriacao;
            $dataAtualizacao = $record->dataAtualizacao;

            $data_arr[] = array(
                "id" => $id,
                "nome" => $nome,
                "email" => $email,
                "sexo" => $sexo,
                "status" => $status,
                "confirmacaoEmail" => $confirmacaoEmail,
                "dataCriacao" => $dataCriacao,
                "dataAtualizacao" => $dataAtualizacao,
            );
        }

        return $this->response(200, "MSG000151", ['total' => $totalRecordsFilter, 'data' => $data_arr]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function cadastrar(Request $request)
    {
        try {

            //validar
            $validar = Usuario::$atualizarPerfilValidar;
            $validar['email'] = $validar['email'] . '|unique:usuarios'; 
            $validator = Validator::make(
                $request->all(),
                $validar,
                Usuario::$validarMensagens);

            if ($validator->fails()) {
                return $this->erros("MSG000071", $validator);
            }

            //cadastrar
            DB::beginTransaction();
            
            $usuario = new Usuario;
            $usuario->nome = $request->nome;
            $usuario->email = $request->email;
            $usuario->password = Hash::make($request->senha);
            $usuario->token = Str::random(32);
            $usuario->dataNascimento = new DateTime();
            $usuario->status = 1;
            $usuario->sexo = is_null($request->sexo) ? null : $request->sexo;
            $usuario->usuarioCriacaoId = JWTAuth::user()->id;
            $usuario->usuarioAtualizacaoId = JWTAuth::user()->id;
            $usuario->confirmacaoEmail = $request->confirmacaoEmail;
            $usuario->save();

            $role = Role::findOrFail($request->grupoId);
            $usuario->roles()->attach($role);
            $usuario->save();

            DB::commit();

            return $this->response(201, "MSG000151", $usuario);

        } catch (Exception $e) {

            DB::rollback();

            return $this->response(404, "MSG000131");
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function obter($id)
    {
        try {
            
            //validar
            if (is_null($id)) {
                return $this->response(404, "MSG000152");
            }

            $usuario = Usuario::find($id);
            $usuarioDto = [
                "id" => $usuario->id,
                "nome" => $usuario->nome,
                "email" => $usuario->email,
                "token" => $usuario->token,
                "dataNascimento" => $usuario->dataNascimento,
                "confirmacaoEmail" => $usuario->confirmacaoEmail,
                "sexo" => $usuario->sexo,
                "status" => $usuario->status,
                "dataCriacao" => $usuario->created_at,
                "dataAtualizacao" => $usuario->updated_at,
                "usuarioCriacaoId" => $usuario->usuarioCriacaoId,
                "usuarioAtualizacaoId" => $usuario->usuarioAtualizacaoId,
                "grupoId" => is_null($usuario->roles->first()) ? null : $usuario->roles->first()->id
            ];

            if (!is_null(($usuario))) {

                return $this->response(200, "MSG000151", $usuarioDto);

            } else {

                return $this->response(404, "MSG000112");

            }

        } catch (Exception $ex) {
            return $this->response(404, "MSG000131");
        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function atualizar(Request $request, $id)
    {
        try {

            if (is_null($id)) {
                return $this->response(404, "MSG000152");
            }

            //validar
            $validar = Usuario::$atualizarPerfilValidar;
            $validar['email'] = $validar['email'] . '|unique:usuarios'. ',email,' . $id; 
            $validator = Validator::make(
                $request->all(),
                $validar,
                Usuario::$validarMensagens);

            if ($validator->fails()) {
                return $this->erros("MSG000071", $validator);
            }

            $usuario = Usuario::find($id);

            if (!is_null(($usuario))) {

                //cadastrar
                DB::beginTransaction();
                
                $usuario->nome = $request->nome;
                $usuario->email = $request->email;
                $usuario->password = Hash::make($request->senha);;
                $usuario->dataNascimento = $request->dataNascimento;
                $usuario->sexo = $request->sexo;
                $usuario->usuarioAtualizacaoId = JWTAuth::user()->id;
                $usuario->confirmacaoEmail = $request->confirmacaoEmail;
                $usuario->save();
                
                $role = Role::findOrFail($request->grupoId);
                
                if(!$usuario->hasRole($role->name)){
                    $usuario->detachRoles($usuario->roles);
                    $usuario->roles()->attach($role);
                    $usuario->save();
                 }

                DB::commit();

                $usuario->grupoId = $role->id;

                return $this->response(200, "MSG000151", $usuario);

            } else {

                return $this->response(404, "MSG000112");

            }

        } catch (Exception $ex) {
            return $this->response(404, "MSG000131");
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
    public function deletar($id)
    {

        try {

            if (is_null($id)) {
                return $this->response(404, "MSG000152");
            }

            $usuario = Usuario::find($id);

            if (!is_null(($usuario))) {

                $usuario->delete();
                return $this->response(200, "MSG000144");

            } else {

                return $this->response(404, "MSG000112");

            }

        } catch (Exception $ex) {
            return $this->response(404, "MSG000131");
        }
    }

    public function nome($id)
    {
        try {

            if (is_null($id)) {
                return $this->response(404, "MSG000152");
            }

            $usuario = Usuario::select('nome')->where('id',$id)->first();

            if (!is_null(($usuario))) {

                return $this->response(200, "MSG000144", $usuario->nome);

            } else {

                return $this->response(404, "MSG000112");

            }

        } catch (Exception $ex) {
            return $this->response(404, "MSG000131");
        }

    }

    public function status(Request $request, $id)
    {
        try {

            if (is_null($id)) {
                return $this->response(404, "MSG000152");
            }

            $usuario = Usuario::find($id);

            if (!is_null(($usuario))) {

                $usuario->status = $request->status;
                $usuario->save();

                return $this->response(200, "MSG000144", $usuario);

            } else {

                return $this->response(404, "MSG000112");

            }

        } catch (Exception $ex) {
            return $this->response(404, "MSG000131");
        }

    }

    public function grupo($id, Request $request)
    {
        $grupoId = $request->id;
        if (is_null($id) || $grupoId) {
            return $this->response(404, "MSG000152");
        }

        $usuario = Usuario::find($id);
        $role = Role::find($request->id);

        if (!is_null(($usuario)) || !is_null(($role))) {

            $usuario->roles()->attach($role->id);
            $usuario->save();

            return $this->response(200, "MSG000144", $usuario);

        } else {

            return $this->response(404, "MSG000112");

        }

        return response()->json("created");
    }

}
