<?php
namespace App\Services;

use App\Http\Controllers\Controller;
use App\Models\Security\SecRole;
use App\Models\Master\MstCompany;
use App\Models\Master\MstBank;
use App\Models\Master\MstBranch;
use App\Models\Master\MstBrand;
use App\Models\Master\MstJabatan;
use App\Models\Master\MstModel;
use App\Models\Master\MstSupplier;
use App\Models\Master\MstApprover;
use App\Models\Legal\Master\MstKpknl;
use App\Models\Legal\Master\MstBalaiLelang;
use App\Models\Legal\Master\MstPic;
use App\Models\Litigasi\Master\MstPengadilan;
use App\Models\Master\MstKategoriBastk;
use App\Models\Spd\Master\MstCollector;
use App\Models\Security\SecUser;
use App\Models\Fm\Master\MstBankSupplier;
use App\Models\Fm\Master\MstPicOffering;
use App\Models\Spd\Master\MstTypeSpbu;
use App\Models\Spd\Master\MstProvinsi;
use App\Models\Spd\Master\MstKabupaten;
use App\Models\Spd\Master\MstMerk;
use App\Models\Spd\Master\MstDepartemen;
use App\Models\Spd\Master\MstCity;
use App\Models\Spd\Master\MstSpbu;
use App\Models\Spd\Master\MstCarType;
use App\Models\Spd\Master\MstKaryawan;
use App\Models\Spd\Master\VWApproverSpd;
use App\Models\Rp\Master\MstBalaiLelangJF;
use App\Models\Fidusia\Master\MstNotaris;
use App\Models\Surat\Master\MstJenisSurat;
use App\Models\Surat\Master\MstBpn;
use App\Models\Surat\Master\MstPool;
use App\Models\DeskCall\Master\MstNegosiasi;
use App\Models\DeskCall\Master\MstSubNegosiasi;
use App\Models\DeskCall\Master\MstHubunganDebitur;
use App\Models\DeskCall\Master\VWMstDeskCall;
use App\Models\CustCare\Master\MstCsJenisPengaduan;
use App\Models\CustCare\Master\MstCsJenisMedia;

use Illuminate\Support\Facades\DB;


class ComboController extends Controller
{
    public function getRole(){
      	$sql = SecRole::select([
            'RoleCode as id','RoleName as text'
        ])->orderBy("RoleCode");
    
        return $this->libs->renderDataCombo($sql);
    }

	public function getCompany(){
		$sql = MstCompany::select([
			'CompanyId as id','CompanyName as text'
	  	])->where(["Status"=>'1'])
		->orderBy("CompanyId");
  
		return $this->libs->renderDataCombo($sql);
	  }

	  public function getCollector(){
		$sql = MstCollector::select([
			'CollectorId as id','CollectorName as text'
	  	])->where(["Status"=>'1'])
		->orderBy("CollectorName");
  
		return $this->libs->renderDataCombo($sql);
	  }


	  public function getBank(){
		$sql = MstBank::select([
			'BankId as id','BankName as text'
	  	])->orderBy("BankId");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  public function getApprover(){
		$sql = SecUser::select([
			'UserId as id','EmpName as text'
			])->orderBy("UserId");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  public function getBranch(){
		$sql = MstBranch::select([
			'BranchId as id','BranchName as text'
	  	])->orderBy("BranchId");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  public function getJabatan(){
		$sql = MstJabatan::select([
			'JabatanId as id','NamaJabatan as text'
	  	])->orderBy("JabatanId");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  //public function getDepartment(){
		//$sql = MstDepartemen::select([
			//'DeptId as id','DeptName as text'
		  //]);
		  //if (! $this->superAdmin) {
			//$sql->where("DeptId",$this->deptId);
			//}	
		//$sql->orderBy("DeptId");
  
		//return $this->libs->renderDataCombo($sql);

	  public function getDepartment(){
		if($this->deptId=="D0014" || $this->deptId=="D0015" || $this->deptId=="D0018" || $this->deptId=="D0019" || $this->superAdmin) {
			$sql = MstDepartemen::select([
				'DeptId as id','DeptName as text'
			  ]);
			  $sql->orderBy("DeptId");
  			  return $this->libs->renderDataCombo($sql);
		}else{	
		  if (! $this->superAdmin) {
			$sql = MstDepartemen::select([
				'DeptId as id','DeptName as text'
			  ]);
			$sql->where("DeptId",$this->deptId);
			$sql->orderBy("DeptId");
			return $this->libs->renderDataCombo($sql);
		   }
		}  
	  } 

	  public function getAllDepartment(){
			$sql = MstDepartemen::select([
				'DeptId as id','DeptName as text'
			]);
			$sql->orderBy("DeptId");
  			return $this->libs->renderDataCombo($sql);
 	} 

	  public function getSupplier(){
		$sql = MstSupplier::select([
			'SupplierId as id','SupplierName as text'
		  ])->where("StatusJtii",'1')
		   // ->whereNotIn("SupplierId",["PC033","PC059"])
		    ->orderBy("SupplierId");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  public function getBankSupplier(){
		$sql = MstBankSupplier::select([
			'Code as id','Name as text'
		  ])->where("Status",'1')->orderBy("Name");
  
		return $this->libs->renderDataCombo($sql);
	  } 


	  public function getSupplierJF(){
		$sql = MstSupplier::select([
			'SupplierId as id','SupplierName as text'
		  ])->where("Status",'1')
		 // ->whereNotIn("SupplierId",["PC060"])
		  ->orderBy("SupplierId");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  public function getPic(){
		$sql = MstApprover::select([
			'ApproverId as id','EmpName as text'
		  ])->leftjoin("SecUser","MstApprover.UserId","SecUser.UserId")
		  ->where(["MstApprover.Status"=>'1',"MstApprover.TypeApprover"=>'C'])->orderBy("MstApprover.UserId");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  public function getPengadilan(){
		$sql = MstPengadilan::select([
			'PengadilanId as id','NamaPengadilan as text'
		  ])->where(["Status"=>'1'])->orderBy("NamaPengadilan");
  
		return $this->libs->renderDataCombo($sql);
	  }


	  public function getPicCetakOffering(){
		$sql = MstPicOffering::select([
			'PicNik as id','PicNama as text'
		  ])->where("Status",'1')->orderBy("PicNama");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  public function getPicLelang(){
		$sql = MstPic::select([
			'PicNik as id','PicName as text'
		  ])->orderBy("PicNik");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	public function getAllApprover(){
		$sql = VWApproverSpd::select([
			'UserId as id','EmpName as text'
		  ]);
		  if (! $this->superAdmin) {
			$sql->where("DeptId",$this->deptId);
		  }	
		  $sql->distinct();
		  $sql->orderBy("UserId");
  
		return $this->libs->renderDataCombo($sql);
	} 

	  public function getLegalApprover(){
		$sql = MstApprover::select([
			'ApproverId as id','EmpName as text'
		  ])->leftjoin("SecUser","MstApprover.UserId","SecUser.UserId")
		  ->where(["MstApprover.Status"=>'1',"MstApprover.TypeApprover"=>'L'])->orderBy("MstApprover.UserId");
  
		  $sql2 = MstApprover::select([
			'ApproverId as id','EmpName as text'
		  ])->leftjoin("SecUser","MstApprover.UserId","SecUser.UserId")
		  ->where([
			"MstApprover.Status"=>'1',
			"MstApprover.TypeApprover"=>'N',
			"MstApprover.TypeUser"=>'2'
		  ]);

		return $this->libs->renderDataCombo($sql->union($sql2));
		//return $this->libs->renderDataCombo($sql);
	  } 


	  public function getMikroApprover(){

		$sql = MstApprover::select([
			'ApproverId as id','EmpName as text'
		  ])->leftjoin("SecUser","MstApprover.UserId","SecUser.UserId")
		  ->where([
			"MstApprover.Status"=>'1',
			"MstApprover.TypeApprover"=>'N',
			"MstApprover.TypeUser"=>'2'
			//"MstApprover.Team"=>$team,
		  ]);
		//if ($this->teamFor=="E"){
		//	$sql->whereIn("MstApprover.Team",["A","B"]);	 
		//}else{
		//	$sql->where("MstApprover.Team",$this->teamFor);
		//}	
		$sql->orderBy("MstApprover.UserId");
  
		return $this->libs->renderDataCombo($sql);
	  } 

	  public function getLitigasiApprover(){

		$sql = MstApprover::select([
			'ApproverId as id','EmpName as text'
		  ])->leftjoin("SecUser","MstApprover.UserId","SecUser.UserId")
		  ->where([
			"MstApprover.Status"=>'1',
			"MstApprover.TypeApprover"=>'T',
			"MstApprover.TypeUser"=>'2'
		  ]);
		$sql->orderBy("MstApprover.UserId");
 
		return $this->libs->renderDataCombo($sql);
	  }

	public function getInternalAuditApprover(){

		$sql = MstApprover::select([
			'ApproverId as id','EmpName as text'
		])->leftjoin("SecUser","MstApprover.UserId","SecUser.UserId")
		->where([
			"MstApprover.Status"=>'1',
			"MstApprover.TypeApprover"=>'I',
			"MstApprover.TypeUser"=>'2'
		]);
		$sql->orderBy("MstApprover.UserId");

		return $this->libs->renderDataCombo($sql);
	}	  

	  public function getKpknl(){
		$sql = MstKpknl::select([
			'KpknlId as id','KpknlName as text'
		  ])->where("Status",'1')->orderBy("KpknlName");
  
		return $this->libs->renderDataCombo($sql);
	  } 


	  public function getBalaiLelang(){
		$sql = MstBalaiLelang::select([
			'BalaiLelangId as id','BalaiLelangName as text'
		  ])->where("Status",'1')->orderBy("BalaiLelangId");
  
		return $this->libs->renderDataCombo($sql);
	  } 
 


	public function getCompanyBlackList($collectorId){
		$sql = MstCompany::select([
			'CompanyId as id','CompanyName as text'
		])->where("Status",'1');
		
		$dataBlackList = $this->blackListCollector($collectorId);
		
        if(count($dataBlackList) > 0){
            $sql->whereNotIn("CompanyId",$dataBlackList);
        }
		
		$sql->orderBy("CompanyId");
  
		return $this->libs->renderDataCombo($sql);
	}
	  	  
	public function getBrand(){
		$sql = MstBrand::select([
			'BrandId as id','BrandName as text'
	  	])->orderBy("BrandId");
  
		return $this->libs->renderDataCombo($sql);
	}
	
	public function getModelx($brandId){
		$sql = MstModel::select([
			'ModelId as id','ModelName as text'
	  	])->where("BrandId",$brandId)
		->orderBy("ModelId");
  
		return $this->libs->renderDataCombo($sql);
	}

	public function getBranchx($companyId){
		$sql = MstBranch::select([
			'BranchId as id','BranchName as text'
	  	])->where([
			"CompanyId" => $companyId,
			"Status" => '1'
		])->orderBy("BranchId");
  
		return $this->libs->renderDataCombo($sql);
	  }
	  
	  public function getcategory($categoryKend){
		//public function getcategory(){
		$sql = MstKategoriBastk::select([
			'CategoryId as id','CategoryName as text'
	  	])->where("CategoryKend",$categoryKend)
	  //])->where("CategoryKend",2);
		->orderBy("CategoryId");
		return $this->libs->renderDataCombo($sql);
	}

	public function getSpbuType(){
		$sql = MstTypeSpbu::select([
			'TypeName as id','TypeName as text'
	  	])->orderBy("TypeName");
  
		return $this->libs->renderDataCombo($sql);
	} 

	  
	public function getProvinci(){
		$sql = MstProvinsi::select([
			'ProvinciName as id','ProvinciName as text'
	  	])->orderBy("ProvinciName");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getProvinciNew(){
		$sql = MstProvinsi::select([
			'ProvinciId as id','ProvinciName as text'
	  	])->orderBy("ProvinciId");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getKabupaten($provinciId){
		$sql = MstKabupaten::select([
			'KabupatenId as id','KabupatenName as text'
	  	])->where("ProvinciId",$provinciId)
		->orderBy("KabupatenId");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getKabupatenNew(){
		$sql = MstKabupaten::select([
			'KabupatenId as id','KabupatenName as text'
	  	])->orderBy("KabupatenId");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getKaryawan(){
		$sql = MstKaryawan::select([
			'NikId as id','Name as text'])
		  ->where(["Status"=>'1'])->orderBy("Name");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getSubOrdinate(){ 
		//$sql = MstKaryawan::select([
			//'NikId as id','Name as text'])
		// ->where(["Status"=>'1',"DeptId"=>$this->deptId])->orderBy("DeptId");
  		
		//return $this->libs->renderDataCombo($sql);
		// jika dept yg login orang mikro/mikro jf, maka tampilan karyawan adalah gabungan
		if($this->deptId=="D0021" || $this->deptId=="D0022") {
			$sql = MstKaryawan::select([
				'NikId as id','Name as text'])
			->where(["Status"=>'1'])
			->whereIn("DeptId",['D0021','D0022']);
			return $this->libs->renderDataCombo($sql);
		}else if($this->deptId=="D0009" ) {	// custody
			$sql = MstKaryawan::select([
				'NikId as id','Name as text'])
			->where(["Status"=>'1'])
			->whereIn("DeptId",['D0009','D0001']); // custody dan acc
			return $this->libs->renderDataCombo($sql);
		}else{	
			if (! $this->superAdmin) {	
				$sql = MstKaryawan::select([
					'NikId as id','Name as text'])
				->where(["Status"=>'1',"DeptId"=>$this->deptId]); 
			}else{
				$sql = MstKaryawan::select([
					'NikId as id','Name as text'])
				->where(["Status"=>'1']); 
			}
			$sql2 = MstKaryawan::select([
				'NikId as id','Name as text'])
				->where(["Status"=>'1'])
				->whereIn("NikId",['1021','1345','1360','20180001','20200001','20220010','20230011','20240008','1011','1002','1006']);
				//$sql = $sql->union($sql2)->get();  
			return $this->libs->renderDataCombo($sql->union($sql2));
		}	
	} 

	public function getKotaTujuan(){
		$sql = MstCity::select([
		   'CityId as id', DB::Raw('CONCAT("CityName",\' - \',"Provinsi") as text')])
		  ->where("Status",'1')->orderBy("CityName");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getSpbu(){
		$sql = MstSpbu::select([
			'SpbuId as id', DB::Raw('CONCAT("SpbuCode",\' - \',"City",\' - \',"SpbuName") as text')])
		  ->where("Status",'1')->orderBy("SpbuCode","asc");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getMerk(){
		$sql = MstMerk::select([
			'MerkName as id','MerkName as text'
	  	])->orderBy("MerkName");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getModel(){
		$sql = MstCarType::select([
			'TypeId as id', DB::Raw('CONCAT("MerkName",\' - \',"ModelName") as text')])
		  ->where("Status",'1')->orderBy("MerkName","Desc");
  
		return $this->libs->renderDataCombo($sql);
	}

	public function getPoolId(){
		$sql = MstBalaiLelangJF::select([
			'BalaiLelangId as id','BalaiLelangName as text'])
		  ->where(["Status"=>'1'])->orderBy("BalaiLelangName");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getNotaris(){
		$sql = MstNotaris::select([
			'NotarisId as id','NamaNotaris as text'])
		  ->where(["Status"=>'1'])->orderBy("NamaNotaris");
  
		return $this->libs->renderDataCombo($sql);
	} 

	public function getDaftarSurat(){
		if($this->deptId=="D0021" || $this->deptId=="D0022") {
			$sql = MstJenisSurat::select([
				'SuratId as id','NamaSurat as text'])
				->whereIn("SuratId",['JS011','JS012','JS013','JS014']);		
		}else if($this->deptId=="D0025" || $this->deptId=="D0004"){	
			$sql = MstJenisSurat::select([
				'SuratId as id','NamaSurat as text'])
				->whereIn("SuratId",['JS001','JS002']);	
		/*
		}else{	
			$sql = MstJenisSurat::select([
				'SuratId as id','NamaSurat as text']);
				if (! $this->superAdmin) {
					$sql->where("DeptId",$this->deptId);
					$sql->where(["Status"=>'1'])
					->orderBy("NamaSurat");
				}	
		}
		*/
		}else{	
    		$sql = MstJenisSurat::select([
        		'SuratId as id','NamaSurat as text'])
        		// JS005 = "Surat Izin Khusus Karyawan Internal Custody" intentionally excluded
        		->whereNotIn("SuratId",['JS005']);
        		if (! $this->superAdmin) {
            		$sql->where("DeptId",$this->deptId);
            		$sql->where(["Status"=>'1'])
            		->orderBy("NamaSurat");
        		}	
		}				
	  
		return $this->libs->renderDataCombo($sql);
	} 	

	public function getDaftarBpn(){
		$sql = MstBpn::select([
			'BpnId as id','NamaBpn as text'])
			->where(["Status"=>'1'])->orderBy("NamaBpn");

		return $this->libs->renderDataCombo($sql);
	} 

	public function getDaftarPool(){
		$sql = Mstpool::select([
			'PoolId as id',DB::Raw('CONCAT("Pool",\' - \',"PoolName") as text')])
			->where(["Status"=>'1'])->orderBy("Pool");

		return $this->libs->renderDataCombo($sql);
	} 

	public function getNegosiasiDebitur(){
		$sql = MstNegosiasi::select([
			'NegoId as id','NamaNegosiasi as text'])
			->where(["Status"=>'1'])->orderBy("NegoId");

		return $this->libs->renderDataCombo($sql);
	} 

	public function getHubunganDebitur(){
		$sql = MstHubunganDebitur::select([
			'HubunganId as id','NamaHubungan as text'])
			->where(["Status"=>'1'])->orderBy("HubunganId");

		return $this->libs->renderDataCombo($sql);
	} 

	public function getSubNegosiasiDebitur($negoId){
		$sql = MstsubNegosiasi::select([
			'SubNegoId as id','NamaSubNegosiasi as text'])
			->where(["Status"=>'1'])
			->where(["NegoId"=>$negoId])
			->orderBy("SubNegoId");

		return $this->libs->renderDataCombo($sql);
	} 

	public function getSubNegosiasiDebiturContected(){
		$sql = MstsubNegosiasi::select([
			'SubNegoId as id','NamaSubNegosiasi as text'])
			->where(["Status"=>'1'])
			->whereIn("SubNegoId",['S0007','S0008','S0009','S0010','S0014','S0015','S0016','S0016','S0018'])
			->orderBy("SubNegoId");

		return $this->libs->renderDataCombo($sql);
	} 

	public function getListNamaDc() {
		$sql = VWMstDeskCall::select([
			'DcId as id','DcName as text'
		  ]);
		  $sql->orderBy("DcName");
  
		return $this->libs->renderDataCombo($sql);

	}

	public function getJenisPengaduan(){
		$sql = MstCsJenisPengaduan::select([
			'PengaduanId as id','NamaPengaduan as text'])
			->where(["Status"=>'1'])->orderBy("PengaduanId");

		return $this->libs->renderDataCombo($sql);
	} 

	public function getJenisMedia(){
		$sql = MstCsJenisMedia::select([
			'MediaId as id','NamaMedia as text'])
			->where(["Status"=>'1'])->orderBy("MediaId");

		return $this->libs->renderDataCombo($sql);
	} 

	public function getEmpnamePhl(){
		$sql = SecUser::select([
			'UserId as id','EmpName as text'
		])->orderBy("UserId");

		return $this->libs->renderDataCombo($sql);
	} 

}