<?php

/**
 * ApiController
 *
 * @author Benoit Marchand
 * @version 0.1
 * @datecreated 2010-03-14
 * @description Webservice server for Archmage iphone game
 */

require_once 'Zend/Controller/Action.php';

class ApiController extends Zend_Controller_Action
{
       
        protected $appName;
       
        public function init()
        {
            //This webservice is not going to need "views"    
        	$this->_helper->viewRenderer->setNoRender();
            $this->_helper->getHelper('layout')->disableLayout();
        }
        public function preDispatch()
        {
			//***********
        	//Cant figure out the best way to secure the rest web service calls
			//from the iphone for now (http_auth, ssl (too slow), or HMAC-MD5 signature)
			//The authAccepted/signArgs methods are a HMAC without MD5 type of security check,
			//my problem is to be able to send the secure passphrase to the user
			//Apple Push Network Service could possibly work for HMAC.. but im reading its not 100% reliable
        	
			//$this->config = new Zend_Config_Ini("../application/webconfig.ini", "development");
               
        	//if (! $this->authAccepted())				
			//	throw new Exception("private key authentication failed");
			//***********
        	
        }
        
        public function indexAction()
        {    
       
        }
        
        public function combatAction()
        {    
		    //Get infos on mages..
        	$ATK_userid = htmlspecialchars($this->getRequest()->getParam("userATK"), ENT_QUOTES);
		    $DEF_userid = htmlspecialchars($this->getRequest()->getParam("userDEF"), ENT_QUOTES);  

		    //Get infos on heroes..
		    $ATK_Heroes=$this->getHeroInfos($ATK_userid);
		    $DEF_Heroes=$this->getHeroInfos($DEF_userid);
		    
		    //Get infos on creatures..
		    $ATK_Creatures=$this->getCreatureInfos($ATK_userid);
		    $DEF_Creatures=$this->getCreatureInfos($DEF_userid);
        
		    //Generate stack data
        	$ATK_Stacks=$this->GenerateStacks($ATK_Creatures, 'att');
        	$DEF_Stacks=$this->GenerateStacks($DEF_Creatures, 'def');
        	
        	//Calculate battle
        	$BattleData=$this->calculateBattle($ATK_Stacks,$DEF_Stacks);
        	
        	//TODO: save log to DB, table combat_log
        	//$this->saveCombatLog($BattleData);
        	print $BattleData['log'];
        	
        	if ($BattleData['DEF_WON']==true) {
        		print "<br><br>DEFENSE WON WOOHOO!";
        		$this->updateMageStats($ATK_userid,'lost');
        		$this->updateMageStats($DEF_userid,'won');
        	
        	}else{
        		print "<br><br>ATTACK WON OMG!!";
        		$this->updateMageStats($ATK_userid,'won');
        		$this->updateMageStats($DEF_userid,'lost');
        	}
        
        	//Calculate death_toll...
        	//debug set to true... easier not to delete the armies while building :)
        	$this->calculateArmyDeathToll($ATK_userid, $BattleData['ATK_Stacks_Remaining'], true);
        	$this->calculateArmyDeathToll($DEF_userid, $BattleData['DEF_Stacks_Remaining'], true);
        }
        
        public function calculateBattle($ATK_Stacks,$DEF_Stacks) {
  
        	//Record battle log..
        	ob_start();
          	
        	//Record initial total armies net power.
        	$ATK_ArmyPower=$this->calculateArmyPower($ATK_Stacks);
        	$DEF_ArmyPower=$this->calculateArmyPower($DEF_Stacks);
        	
            //Will be used to calculate DeathToll and update armies after combat
        	$ATK_Stacks_Remaining=array();
        	$DEF_Stacks_Remaining=array();
        	
        	//Initialize combat counters
        	$StackNumberATK=$StackNumberDEF=0;
        	$StackNumberTotalATK=count($ATK_Stacks);
        	$StackNumberTotalDEF=count($DEF_Stacks);
        	$Round=0;
        	$resetCpt=0;
        	
        	//Assign uniqueID to each stack (will be used to follow them through combat)
        	$this->assignUID($ATK_Stacks);
        	$this->assignUID($DEF_Stacks);

        	//The ATK/DEF roles can be switched based on initiative rolls.
        	//Here we make sure not to forget who was the original attacker and defender!
        	$ATK_Stacks['type']='atk';
        	$DEF_Stacks['type']='def';
        	
        	//Calculate full battle outcome
        	$BattleOver=false;
        	
        	//Set to true to print all the log to screen
        	$PrintBattleOutput=true;


			//This flag indicates that the stack who defended first is now attacking back
			$IsRetaliation=false;
			$hasRetaliated=false;
			$ATK_WON=false;
			$DEF_WON=false;
			//        	while (!$BattleOver) {
   
			//Continue until there is no stack remaining in either attacking or defending army
			while (!$BattleOver
					||( ($hasRetaliated==false) 
						&& ($ATK_WON==false) 
						&& ($DEF_WON==false))) {			

           		if ($Round==0 && $IsRetaliation==false) {           			
           			//This keeps track who which stack fought with which stack, to later determine stalemate when attackers can't get through
           			$arrStaleMate[] = $ATK_Stacks[$StackNumberATK]['uid']."-".$DEF_Stacks[$StackNumberDEF]['uid'];
           		}
           		
        		if ($PrintBattleOutput) { print "<br>Round #" . ($Round+1) . "<br>"; }				
        		
        		if ($Round==0) {
	        		//set starting initiatives
	        		//print "<pre>";
           	    	//print_r($ATK_Stacks);
           	    	$this->updateStackFatigue($ATK_Stacks,$StackNumberATK,1);
		        	$this->updateStackFatigue($DEF_Stacks,$StackNumberDEF,1);
					
		        	if ($PrintBattleOutput && !$IsRetaliation) {           	
						print "<pre>";
		        		print_r($ATK_Stacks[$StackNumberATK]);
						print "<br> OPPOSING <br>";
						print_r($DEF_Stacks[$StackNumberDEF]);
					}
					
           	    } else {		        	
           	    	//print_r($ATK_Stacks);
           	    	//print "<br>.....StackNumber ATK....".$StackNumberATK."<br>";
		        	//print_r($DEF_Stacks);
           	    	//print "<br>.....StackNumber DEF....".$StackNumberDEF."<br>";				
           	    	           	    	
           	    	//Each round after first, re-determine Initiative based on fatigue
           	    	//Each stack loses initiative equal to it's % 		
           	    	$this->updateStackFatigue($ATK_Stacks,$StackNumberATK,0);    		
		        	$this->updateStackFatigue($DEF_Stacks,$StackNumberDEF,0);

           	 		if ($PrintBattleOutput && !$IsRetaliation) {           	
						print "<pre>";
		        		print_r($ATK_Stacks[$StackNumberATK]);
						print "<br> OPPOSING <br>";
						print_r($DEF_Stacks[$StackNumberDEF]);
					}
           	    }
        		
           	    if ($PrintBattleOutput) {
					print "<br>ATK initiative = " .$ATK_Stacks[$StackNumberATK]['initiative_actual'] . "<br>";
					print "<br>DEF initiative = " .$DEF_Stacks[$StackNumberDEF]['initiative_actual'] . "<br>";
				}
     
				      		
        		if ($IsRetaliation) {
        			//invert attacker and defender for retaliation..
        			$tmpPtr=&$RD_ATK_Stacks;
        			$RD_ATK_Stacks=&$RD_DEF_Stacks;
        			$RD_DEF_Stacks=&$tmpPtr;

        			//invert also the stack counters..
        			///$tmpCpt=$StackNumberATK;
        			///$StackNumberATK=$StackNumberDEF;
        			///$StackNumberDEF=$tmpCpt;	        			
        			$tmpCpt=$RD_StackNumberATK;
        			$RD_StackNumberATK=$RD_StackNumberDEF;
        			$RD_StackNumberDEF=$tmpCpt;        			
        			
        			$hasRetaliated=true;    			
        		} else {
        			
        			//Check which stack attacks first based on determined initiative  
        			if ($ATK_Stacks[$StackNumberATK]['initiative_actual'] >= $DEF_Stacks[$StackNumberDEF]['initiative_actual']) {
						$RD_ATK_Stacks=&$ATK_Stacks;
	        			$RD_DEF_Stacks=&$DEF_Stacks;
						$RD_StackNumberATK=$StackNumberATK;
	        			$RD_StackNumberDEF=$StackNumberDEF;
	        			if ($PrintBattleOutput) { print "<br>ATK is first<br>"; }
	        		} else {
	        			$RD_ATK_Stacks=&$DEF_Stacks;
	        			$RD_DEF_Stacks=&$ATK_Stacks;
						$RD_StackNumberATK=$StackNumberDEF;
	        			$RD_StackNumberDEF=$StackNumberATK;
	        			if ($PrintBattleOutput) { print "<brDEF is first<br>"; }	        			
	           		}
        		}

        		//Determine total attacker strenght
        		///$att_total=$this->getTotalAttack($RD_ATK_Stacks, $StackNumberATK);
        		$att_total=$this->getTotalAttack($RD_ATK_Stacks, $RD_StackNumberATK);
        		
        		//Determine total defender strenght
        		///$def_total=$this->getTotalDefense($RD_DEF_Stacks, $StackNumberDEF);
        		$def_total=$this->getTotalDefense($RD_DEF_Stacks, $RD_StackNumberDEF);
        		
        		//calculate Atk and Def total powers, considering fatigue
        		if ($Round>0) {
        			///if ($PrintBattleOutput) { print "<br>Attack total = ".$att_total.", - Fatigue (" . $RD_ATK_Stacks[$StackNumberATK]['pct_fatigue'] . "%) = "; }	        			
        			if ($PrintBattleOutput) { print "<br>Attack total = ".$att_total.", - Fatigue (" . $RD_ATK_Stacks[$RD_StackNumberATK]['pct_fatigue'] . "%) = "; }	        			
        			//Subtract fatigue if after first round (att does not get fatigue at first attack)
        			///$att_total-=$att_total*$RD_ATK_Stacks[$StackNumberATK]['pct_fatigue']/100;	
        			$att_total-=$att_total*$RD_ATK_Stacks[$RD_StackNumberATK]['pct_fatigue']/100;	
        			if ($PrintBattleOutput) { print $att_total."<br>"; }

        		
        			///if ($PrintBattleOutput) { print "<br>Defense total = ".$def_total.", - Fatigue (" . $RD_DEF_Stacks[$StackNumberDEF]['pct_fatigue'] . "%) = "; }        			        		
        			if ($PrintBattleOutput) { print "<br>Defense total = ".$def_total.", - Fatigue (" . $RD_DEF_Stacks[$RD_StackNumberDEF]['pct_fatigue'] . "%) = "; }        			        		
        			//Subtract fatigue if after first round (def does not get fatigue at first attack)
        			///$def_total-=$def_total*$RD_DEF_Stacks[$StackNumberDEF]['pct_fatigue']/100;						        			
        			$def_total-=$def_total*$RD_DEF_Stacks[$RD_StackNumberDEF]['pct_fatigue']/100;						        			
        			if ($PrintBattleOutput) { print $def_total."<br>"; }
        		
        		} else {        		
        			if ($PrintBattleOutput) { print "<br>Attack total = ".$att_total."<br>"; }
        			if ($PrintBattleOutput) { print "<br>Defense total = ".$def_total."<br>"; }
        		}
        		
				//Determine relative strenght
				$pct_str=$this->getAtkModifier($att_total,$def_total);
					
				if ($PrintBattleOutput) { print "<br>Percent of relative strenght = " . $pct_str . "% <br>"; }
        		
				//Determine attacker damage
        		///$att_dmg=$this->calculateDamage($RD_ATK_Stacks[$StackNumberATK]['damage'])*$RD_ATK_Stacks[$StackNumberATK]['number_left'];
        		$att_dmg=$this->calculateDamage($RD_ATK_Stacks[$RD_StackNumberATK]['damage'])*$RD_ATK_Stacks[$RD_StackNumberATK]['number_left'];
        		///if ($PrintBattleOutput) { print "<br>Attack string = " . $RD_ATK_Stacks[$StackNumberATK]['damage'] . " -- Attack damage = " . $att_dmg . "dmg<br>"; } 
        		if ($PrintBattleOutput) { print "<br>Attack string = " . $RD_ATK_Stacks[$RD_StackNumberATK]['damage'] . " -- Attack damage = " . $att_dmg . "dmg<br>"; } 
        		
        		//Add (or subtract) relative strenght
        		$att_dmg += ($att_dmg * $pct_str / 100);
        		if ($PrintBattleOutput) {
        			print "<br>Attack damage with relative strenght = " . $att_dmg."dmg<br>";
					///print "<br>Defense HP : " . $RD_DEF_Stacks[$StackNumberDEF]['hit_points'] . "<br>";
					print "<br>Defense HP : " . $RD_DEF_Stacks[$RD_StackNumberDEF]['hit_points'] . "<br>";
        		}
				//print "<br>StackNumberATK=".$StackNumberATK;
				//print "<br>StackNumberDEF=".$StackNumberDEF;
				///$this->calculateCasualties($RD_DEF_Stacks,$StackNumberDEF,$att_dmg,$PrintBattleOutput);
				$this->calculateCasualties($RD_DEF_Stacks,$RD_StackNumberDEF,$att_dmg,$PrintBattleOutput);
				
        		/*
        		//If attacking stack has 0 or less numbers, end stack fight
        		if ($RD_ATK_Stacks[$StackNumberATK]['number_left'] <= 0) {  ///#####NOT SURE THIS IF IS CALLED EVER!!
        			if ($PrintBattleOutput) { print "<br>Stack Battle Over!!<br>"; }
        			$Round=0;
        			
        			//if there is another ATK stack, his turn is up
        			//else, if we are here, attackers did not manage to kill defenders, end combat
        			if ($StackNumberATK < ($StackNumberTotalATK-1))        				
        				$StackNumberATK++;
        			else
        				$BattleOver=true;
        			
        			//if there is another DEF stack, his turn is up
        			//else, it's up to the first stack to defend again
        			if ($StackNumberDEF < ($StackNumberTotalDEF-1))
        				$StackNumberDEF++;
        			else
        				$StackNumberDEF=0;
        			
        			if ($BattleOver==true && $PrintBattleOutput) 
        				print "<br>ATTACKERS DID NOT PIERCE DEFENCE! BATTLE OVER!<br><br><br><br>"; 
        			else if ($BattleOver==false && $PrintBattleOutput)
        				print "<br>NO WINNING STACK! NEXT STACKS ARE UP!!<br>- - - - - -<br><br><br><br>"; 
        			
        			$IsRetaliation=false;
        		
        		//If defending stack has 0 or less numbers, end stack fight
        		} else if ($RD_DEF_Stacks[$StackNumberDEF]['number_left'] <= 0) { ///#####NOT SURE THIS IF IS CALLED EVER!!
        		*/
        		///if ($RD_DEF_Stacks[$StackNumberDEF]['number_left'] <= 0) { 
        		if ($RD_DEF_Stacks[$RD_StackNumberDEF]['number_left'] <= 0) { 
        			/*
        			if ($BattleOver==true && $PrintBattleOutput  
        					&& $RD_ATK_Stacks[$StackNumberATK]['number_left'] >= 1
        					&& $RD_ATK_Stacks['type']=='atk') { ///#####NOT SURE THIS IF IS CALLED EVER!!
        				print "<br>ATTACKERS WON! BATTLE OVER!<br><br><br><br>";
        				$ATK_WON=true; 
        			} else if ($BattleOver==true && $PrintBattleOutput
        					&& $RD_ATK_Stacks[$StackNumberATK]['number_left'] >= 1
        					&& $RD_ATK_Stacks['type']=='def') { ///#####NOT SURE THIS IF IS CALLED EVER!!
        				print "<br>DEFENDERS WON! BATTLE OVER!<br><br><br><br>"; 
        				$DEF_WON=true; 
        			} else if ($BattleOver==true && $PrintBattleOutput){ ///#####NOT SURE THIS IF IS CALLED EVER!!
        				print "<br>ATTACKERS DID NOT PIERCE DEFENCE! BATTLE OVER!<br><br><br><br>"; 
        			} else if ($BattleOver==false && $PrintBattleOutput
        			*/
        			///if ($BattleOver==false && $PrintBattleOutput
        			///		&& $RD_ATK_Stacks[$StackNumberATK]['number_left'] >= 1 
        			///		&& $RD_ATK_Stacks['type']=='atk') { 
        			///	print "<br>ATTACKERS WON! NEXT STACKS ARE UP!<br><br><br><br>";	        			
        			///} else if ($BattleOver==false && $PrintBattleOutput
        			///		&& $RD_ATK_Stacks[$StackNumberATK]['number_left'] >= 1 
        			///		&& $RD_ATK_Stacks['type']=='def') { 
        			///	print "<br>DEFENDERS WON! NEXT STACKS ARE UP!<br><br><br><br>";	
        			if ($BattleOver==false && $PrintBattleOutput
        					&& $RD_ATK_Stacks[$RD_StackNumberATK]['number_left'] >= 1 
        					&& $RD_ATK_Stacks['type']=='atk') { 
        				print "<br>ATTACKERS WON! NEXT STACKS ARE UP!<br><br><br><br>";	        			

	        			//if there is another DEF stack, his turn is up
	        			//else, it's up to the first stack to defend again
	        			///if ($StackNumberDEF < ($StackNumberTotalDEF-1)) {
	        			if ($StackNumberDEF < ($StackNumberTotalDEF-1)) {
	        				$StackNumberDEF++;
	        			///} else if (isset($RD_DEF_Stacks[$StackNumberDEF]) && $RD_DEF_Stacks[$StackNumberDEF]['number_left']>0) {
	        			} else if (isset($RD_DEF_Stacks[$RD_StackNumberDEF]) && $RD_DEF_Stacks[$RD_StackNumberDEF]['number_left']>0) {
	        				$StackNumberDEF=0;
	        			} else if ($StackNumberTotalDEF > 1) { //???
	        				$StackNumberDEF=0;
	        			} else {
	        				//if we reach this point, all defenders have been killed
	        				$BattleOver=true;
	        				$ATK_WON=true; 
	        			}
        			
        			} else if ($BattleOver==false && $PrintBattleOutput
        					&& $RD_ATK_Stacks[$RD_StackNumberATK]['number_left'] >= 1 
        					&& $RD_ATK_Stacks['type']=='def') { 
        				print "<br>DEFENDERS WON! NEXT STACKS ARE UP!<br><br><br><br>";	
     			
	        			//if there is another ATK stack, his turn is up
	        			//else if we are here, attackers did not manage to kill defenders, end combat
	        			///if ($StackNumberATK < ($StackNumberTotalATK-1)) {
	        			if ($StackNumberATK < ($StackNumberTotalATK-1)) {
	        				$StackNumberATK++;
	        			} else if ($StackNumberTotalATK > 1) { //???
	        				$StackNumberATK=0; //????
	           			} else {        				
	        				$BattleOver=true;
	        				$DEF_WON=true; 
	        			}        				
        			/*
        			} else if ($BattleOver==false && $PrintBattleOutput){ ///#####NOT SURE THIS IF IS CALLED EVER!!
        				print "<br>NO WINNING STACK! NEXT STACKS ARE UP!!<br>- - - - - -<br><br><br><br>"; 
        			*/
        			}	        				
        				
        			$Round=0;
        			
        			//block A
/*
        			//if there is another ATK stack, his turn is up
        			//else if we are here, attackers did not manage to kill defenders, end combat
        			///if ($StackNumberATK < ($StackNumberTotalATK-1)) {
        			if ($StackNumberATK < ($StackNumberTotalATK-1)) {
        				$StackNumberATK++;
        			} else if ($StackNumberTotalATK > 1) { //???
        				$StackNumberATK=0; //????
           			} else {        				
        				$BattleOver=true;
        				$DEF_WON=true; 
        			}
*/
/*
        			//block B
        			//if there is another DEF stack, his turn is up
        			//else, it's up to the first stack to defend again
        			///if ($StackNumberDEF < ($StackNumberTotalDEF-1)) {
        			if ($StackNumberDEF < ($StackNumberTotalDEF-1)) {
        				$StackNumberDEF++;
        			///} else if (isset($RD_DEF_Stacks[$StackNumberDEF]) && $RD_DEF_Stacks[$StackNumberDEF]['number_left']>0) {
        			} else if (isset($RD_DEF_Stacks[$RD_StackNumberDEF]) && $RD_DEF_Stacks[$RD_StackNumberDEF]['number_left']>0) {
        				$StackNumberDEF=0;
        			} else if ($StackNumberTotalDEF > 1) { //???
        				$StackNumberDEF=0;
        			} else {
        				//if we reach this point, all defenders have been killed
        				$BattleOver=true;
        				$ATK_WON=true; 
        			}
  */      			
        			//moved this from in between block A and block B above.. checking if it causes problem?..
        			$this->removeDeadStacks($ATK_Stacks, $DEF_Stacks, $ATK_Stacks_Remaining, $DEF_Stacks_Remaining, $StackNumberATK, $StackNumberDEF);
        			
        			
        			if ($BattleOver && $PrintBattleOutput) { print "BATTLE OVER!"; }	
					
					$this->resetCounters($StackNumberATK,$StackNumberDEF,$StackNumberTotalATK,$StackNumberTotalDEF,$ATK_Stacks,$DEF_Stacks,0,0);

        			$IsRetaliation=false;
        		
        		} else if (!$IsRetaliation) {
        			$IsRetaliation=true;
        			if ($PrintBattleOutput) { print "<br>!!!!!Stack retaliation!!!!!<br>"; }
        		} else if ($Round==4) { ///#####NOT SURE THIS IF IS CALLED EVER!!
        			//If no stack killed after 5 rounds, stop combat between those 2 stacks
        			//and move on to next stack fight
					/*
        			if ($RD_DEF_Stacks[$StackNumberDEF]['number_left'] <= 0 
        					&& $RD_ATK_Stacks[$StackNumberATK]['number_left'] >= 1
        					&& $RD_ATK_Stacks['type']=='atk'
        					&& $PrintBattleOutput) { 
        				
        				print "<br>ATTACKERS WON!";
        				
        				//if there is another ATK stack, his turn is up
        				if ($StackNumberATK < ($StackNumberTotalATK-1)) {
        					$StackNumberATK++;
        					print "NEXT STACKS ARE UP!<br><br><br><br>";
        				} else {
        					$BattleOver=true;	//else, end combat
        					print "WON! BATTLE OVER!<br><br><br><br>"; 
        					$ATK_WON=true;
        				}			 

        			} else if ($RD_DEF_Stacks[$StackNumberDEF]['number_left'] <= 0 
        					&& $RD_ATK_Stacks[$StackNumberATK]['number_left'] >= 1
        					&& $RD_ATK_Stacks['type']=='def'
        					&& $PrintBattleOutput) { 
        				
        				print "<br>DEFENDERS WON!";

        				
	        			//not sure this works properly, need to test with more stacks each side
        				$this->removeDeadStacks($ATK_Stacks, $DEF_Stacks, $ATK_Stacks_Remaining, $DEF_Stacks_Remaining);
        				
						$this->resetCounters($StackNumberATK,$StackNumberDEF,$StackNumberTotalATK,$StackNumberTotalDEF,$ATK_Stacks,$DEF_Stacks);

        				//if there is another ATK stack, his turn is up
        				if ($StackNumberATK < ($StackNumberTotalATK-1)) {
        					$StackNumberATK++;
        					print "NEXT STACKS ARE UP!<br><br><br><br>";
        				} else {
        					$BattleOver=true;	//else, end combat
        					print "WON! BATTLE OVER!<br><br><br><br>"; 
        					$DEF_WON=true;
        				}		        							        						 
        					
        			} else if ($BattleOver==true && $PrintBattleOutput){
        				print "<br>ATTACKERS DID NOT PIERCE DEFENCE! BATTLE OVER!<br><br><br><br>"; 
        			
        			} else if ($BattleOver==false && $PrintBattleOutput){
        			*/
        				print "<br>NO WINNING STACK!<br>";       					        				
        				
        				//not sure i need this? if no winning stack, i dont see how i could have a stack who died...???
        				$this->removeDeadStacks($ATK_Stacks, $DEF_Stacks, $ATK_Stacks_Remaining, $DEF_Stacks_Remaining, $StackNumberATK, $StackNumberDEF);

        				$resetCpt=1;

        				if ($StackNumberATK < ($StackNumberTotalATK-1)) { // if a new stack in the list is available, his turn us up!
	        				print "StackNumberATK=" . $StackNumberATK . "   StackNumberTotalATK=" . $StackNumberTotalATK;
	        				print " NEXT ATK STACK IS UP!!<br>- - - - - -<br><br><br><br>";
	        				$resetCpt=2;
        				} else if ($StackNumberTotalATK > 1) { // else if all stack have attacked, but some are left alive, back to the first available!
        					$StackNumberATK = 0;
        				} else {
	        				$BattleOver=true;  // else, end combat
	        				print " BATTLE OVER!! DEFENSE WON!!<br>- - - - - -<br><br><br><br>";
	        			}	        			
        			//}

	        		///if ($StackNumberDEF < ($StackNumberTotalDEF-1))
	        		////if ($RD_StackNumberDEF < ($StackNumberTotalDEF-1))
        			////	$StackNumberDEF++; //another DEF stack? his turn is up!
        			////else
        			////	$StackNumberDEF=0; //else, first stack's turn to defend

        			print "<br>StackNumberDEF=" . $StackNumberDEF . "   StackNumberTotalDEF=" . $StackNumberTotalDEF."<br>";
        				
        			$Round=0;
        			
					if ($resetCpt==1) {
						$this->resetCounters($StackNumberATK,$StackNumberDEF,$StackNumberTotalATK,$StackNumberTotalDEF,$ATK_Stacks,$DEF_Stacks);						
        				$resetCpt=0;	
					} else if ($resetCpt==2) {
						$this->resetCounters($StackNumberATK,$StackNumberDEF,$StackNumberTotalATK,$StackNumberTotalDEF,$ATK_Stacks,$DEF_Stacks,1,0);						
        				$resetCpt=0;
					}
        			$IsRetaliation=false;	        		
        		} else {
        			//Else combat continues to next round
        			$Round++;
        			$IsRetaliation=false;
        			if ($PrintBattleOutput) { print "<br>Next Round will start!<br>---------<br>"; }        			        				
        		}

				//If the same stacks fought 3 times and a victor has not been declared yet
				//the battle will be stopped and the defenders win.
           		if ($Round==0 && $IsRetaliation==false) {
           			if ($this->checkStalemateReached($arrStaleMate,$PrintBattleOutput,3)==true) {
           				$BattleOver=true;
           				$DEF_WON=true;
           				if ($PrintBattleOutput) { print "DEFENDERS HAVE RESISTED!!!"; }

           				//Exit While loop, combat is over
           				continue;
           			}           			
           		}
           								
			}     

			//Save combat log buffer to variable
        	$data = ob_get_contents();
			ob_end_clean();
          	
        	//not sure i care about calculating the power lost anymore, kept for now

			//print "ATK START POWER:".$ATK_ArmyPower."<br>";
        	//print "DEF START POWER:".$DEF_ArmyPower."<br>";        	
        	//$ATK_ArmyPowerLoss=$ATK_ArmyPower-$this->calculateArmyPower($ATK_Stacks);
        	//$DEF_ArmyPowerLoss=$DEF_ArmyPower-$this->calculateArmyPower($DEF_Stacks);
        	//print "ATK POWER LOSS:".$ATK_ArmyPowerLoss."<br>";
        	//print "DEF POWER LOSS:".$DEF_ArmyPowerLoss."<br>";			
			//$BattleData = array('ATK_WON'=>false,'DEF_WON'=>true,$ATK_ArmyPowerLoss,$DEF_ArmyPowerLoss,'log'=>$data);
			
        	//Update remaining stacks, this will be used to calculate death toll
        	foreach($ATK_Stacks as $stack) { if (is_array($stack)) { $this->updateRemainingStack($ATK_Stacks_Remaining, $stack); } }
        	foreach($DEF_Stacks as $stack) { if (is_array($stack)) { $this->updateRemainingStack($DEF_Stacks_Remaining, $stack); } }
        		
          	$BattleData = array('ATK_WON'=>$ATK_WON,'DEF_WON'=>$DEF_WON,
        							'ATK_Stacks_Remaining'=>$ATK_Stacks_Remaining,'DEF_Stacks_Remaining'=>$DEF_Stacks_Remaining,
        								'log'=>$data);
        	
        	return $BattleData;
        }
        
        private function updateRemainingStack(&$stackRemaining, $stackData) {
        			$stackRemaining[$stackData['code']] = array('pct_death_toll'=>$stackData['pct_death_toll'],
        														'number_total'=>$stackData['number_total'],
        														'number_left'=>$stackData['number_left'],
        														'creature_id'=>$stackData['creature_id']);
		}
        
		private function calculateArmyDeathToll($mageid, $stacks, $debug) {
			// Database credentials
		    $host = 'localhost'; 
		    $db = 'Archmage';    
		    $uid = 'root';
		    $pwd = 'ben0828';
		 
			//connect to the database server   
		    $link = mysql_connect($host, $uid, $pwd) or die("Could not connect");
		   
		    //select the database
		    mysql_select_db($db) or die("Could not select database");

			foreach($stacks as $key=>$stack) {
        		$total_combat_loss = $stack['number_total']-$stack['number_left'];
        	
        		$total_deaths = ceil($total_combat_loss * $stack['pct_death_toll'] / 100);
        	
        		//print "Death Toll for '" . $key . "' = " . $total_deaths . "<br><br>";

			    if ($mageid!="" && $total_deaths>0) {				
				   //update corresponding status with win or lost combat
				   $sql="update mage_has_creature set total_number = total_number - " . $total_deaths . " where mage_id = " . $mageid . " and creature_id = " . $stack['creature_id'];
					
				   //print $sql."<br><br>";
				   
				   if (!$debug)
				   	mysql_query($sql, $link);
				}        	

        	}
        }
        private function calculateArmyPower($stacks) {
        	$total_net_power=0;
        	foreach($stacks as $stack) {
        		$total_net_power+=$stack['net_power']*$stack['number_left'];
        	}
        	return $total_net_power;
        }
        
        private function resetCounters(&$StackNumberATK,&$StackNumberDEF,&$StackNumberTotalATK,&$StackNumberTotalDEF,&$ATK_Stacks,&$DEF_Stacks,$atk=0,$def=0) {        	
        	//reset counters
        	//if ($val)
				//$StackNumberATK=$StackNumberDEF=0;
			//else
				//$StackNumberDEF=0;
			//$StackNumberATK=$atk;
			//$StackNumberDEF=$def;
			$StackNumberTotalATK=count($ATK_Stacks)-1;
        	$StackNumberTotalDEF=count($DEF_Stacks)-1;
        }
        				
        public function updateMageStats($mageid,$type) {
		    if ($mageid!="" && $type!="") {
			
				// Database credentials
			   $host = 'localhost'; 
			   $db = 'Archmage';    
			   $uid = 'root';
			   $pwd = 'ben0828';
			 
				//connect to the database server   
			    $link = mysql_connect($host, $uid, $pwd) or die("Could not connect");
			   
			   //select the database
			   mysql_select_db($db) or die("Could not select database");
			   
			   //update corresponding status with win or lost combat
			   $sql="update mage_stats set combats_".$type." = combats_".$type." + 1 where mage_id = " . $mageid;
			   
			   mysql_query($sql, $link);

			} else {	
				$returnMsg = "Missing mageid or type of update to be made to stats!";
			}        	
        }        
        
        
        private function calculateCasualties(&$stack,$stackNumber,$att_dmg,$PrintBattleOutput) {
	        //Calculate number of defending casualties for this round
	        $casualties = floor($att_dmg / $stack[$stackNumber]['hit_points']);
	        if ($PrintBattleOutput) { print "<br>Defense casulties = " . $casualties . "death(s)<br>"; }
	        
	        //Subtract number of casualties from defending stack
	        $stack[$stackNumber]['number_left'] -= $casualties;
	        if ($PrintBattleOutput) { print "<br>Defense number left = " . $stack[$stackNumber]['number_left'] . "<br>"; }
        }
        
     	//This is a modifier based on the the size of atk vs def. 
     	//The bigger the difference between ATK and DEF stack total power = larger attack bonus
     	//This is an army power bonus
        private function getAtkModifier($att_total,$def_total) {
        		$pct_str=($att_total*100/$def_total)-100;
				
				//defense and offense bonus capped at 50% and -50%
				if ($pct_str > 50)
					$pct_str=50;
				else if ($pct_str < -50)
					$pct_str = -50;
					
				return $pct_str;
        }
        
        //Consider having an army size modifier? (2000 wyverns vs a few dragons has got to distract the dragons even if they ar week?)
        //I could create a creature ability that negates this one (ex: fearless.. ?)
        //private function getArmySizeModifier($att_total_units,$def_total_units) {
        //	
        //}
					
        private function getTotalDefense($stack,$stackNumber) {
        	return $stack[$stackNumber]['def_rating']*$stack[$stackNumber]['number_left'];
        }

        private function getTotalAttack($stack,$stackNumber) {
        	return $stack[$stackNumber]['att_rating']*$stack[$stackNumber]['number_left'];
        }
        
        private function updateStackFatigue(&$stack,$stackNumber,$init) {
			if ($init==true) {
				$stack[$stackNumber]['initiative_actual']=$stack[$stackNumber]['initiative'];
			} else {
				$stack[$stackNumber]['initiative_actual'] -= ($stack[$stackNumber]['pct_fatigue']/100);

				//minimum initiative is 1 for a stack..
				if($stack[$stackNumber]['initiative_actual'] < 1) $stack[$stackNumber]['initiative_actual']=1;  
			}
        }
        
        private function checkStalemateReached($arrStaleMate,$PrintBattleOutput,$StalemateValue) {
           			sort($arrStaleMate);
           			$lastvalue='';
           			$cpt=$cptBig=1;
           			//if ($PrintBattleOutput) { print "<br>Check StaleMate<br>";print_r($arrStaleMate); }
           			foreach($arrStaleMate as $value) {
           				if ($lastvalue==$value) {
           					$cpt++;
           				} else {
           					$cpt=1;
           				}
           				$lastvalue=$value;
           				if ($cpt>$cptBig)
           					$cptBig=$cpt;
           			}
           			
           			//if ($PrintBattleOutput) { print "<br>StaleMate counter:".$cptBig;}
           			
           			//more than 3 same stack fights without one dying?
           			if ($cptBig>=$StalemateValue)
           				return true;
           			else
           				return false;
        }
        
        private function assignUID(&$arr_Stacks) {
        	foreach($arr_Stacks as &$item) {
        		$item['uid']=rand();
        	}
        }
        
        private function removeDeadStacks(&$ATK_Stacks,&$DEF_Stacks,&$ATK_Stacks_Remaining,&$DEF_Stacks_Remaining, &$StackNumberATK, &$StackNumberDEF) {
        	//remove 'type' value from the stack
        	array_pop($ATK_Stacks);
        	array_pop($DEF_Stacks);
        	
        	$i=0;
        	//remove dead stacks
        	while ($i<count($ATK_Stacks)) {
        		if ($ATK_Stacks[$i]['number_left']<=0) {
        			//First keep track of this stack so we can calculate death toll later..
        			$this->updateRemainingStack($ATK_Stacks_Remaining, $ATK_Stacks[$i]);

        			unset($ATK_Stacks[$i]);
        			$StackNumberATK--; // if ATK stack died, need to remove him from counter too!
        		}
        		$i++;	        					
        	}

        	$i=0;
        	while ($i<count($DEF_Stacks)) {
        		if ($DEF_Stacks[$i]['number_left']<=0) {
        			//First keep track of this stack so we can calculate death toll later..
        			$this->updateRemainingStack($DEF_Stacks_Remaining, $DEF_Stacks[$i]);
        			
        			unset($DEF_Stacks[$i]);
        			$StackNumberDEF--; //if DEF stack died, need to remove him from counter too! 
        		}
        		$i++;	        					
        	}	        				
        	
        	$temp_atk_stacks = $ATK_Stacks;
        	$temp_def_stacks = $DEF_Stacks;

        	//empty array before refilling them correctly
        	$ATK_Stacks=array();
        	$DEF_Stacks=array();
        	
        	//Once dead stacks have been removed, re-order by remaining stack power
        	foreach ($temp_atk_stacks as &$blah) {
        		$blah['total_att_rating']=$blah['number_left']*$blah['att_rating'];
				$blah['total_def_rating']=$blah['number_left']*$blah['def_rating'];
        	}
        	$temp_atk_stacks=$this->array_sort($temp_atk_stacks, 'total_att_rating', SORT_DESC);
        	
        	foreach ($temp_def_stacks as &$blah) {
				$blah['total_att_rating']=$blah['number_left']*$blah['att_rating'];
				$blah['total_def_rating']=$blah['number_left']*$blah['def_rating'];
        	}
			$temp_def_stacks=$this->array_sort($temp_def_stacks, 'total_def_rating', SORT_DESC);
        	
			$i=0;
			//then reset the key values of the array
        	foreach ($temp_atk_stacks as $blah) {
        		$ATK_Stacks[$i]=$blah;
        		$i++;
        	}
			$i=0;
        	foreach ($temp_def_stacks as $blah) {
        		$DEF_Stacks[$i]=$blah;
        		$i++;
        	}
        	unset($temp_atk_stacks);
        	unset($temp_def_stacks);
        	
        	//add back 'type' value from the stack
        	$ATK_Stacks['type']='atk';
        	$DEF_Stacks['type']='def';    

        	//these 2 counters can never be lower than 0.. 
        	if ($StackNumberATK<0) { $StackNumberATK=0; }
        	if ($StackNumberDEF<0) { $StackNumberDEF=0; }
        }
        
        public function calculateDamage($StrDmg) {
        	$StrDmg=str_replace(" ","",$StrDmg);
        	$DmgData=explode("+",$StrDmg);
        	$DmgData[0]=explode("d",$DmgData[0]);
			$TotalDmg=0;
			
        	for ($i=0; $i<$DmgData[0][0]; $i++) {
				$TotalDmg += rand(1,$DmgData[0][1]);
			} 
        	
        	if(isset($DmgData[1]))
        		$TotalDmg += $DmgData[1];
        	return $TotalDmg;
        }
        
        public function GenerateStacks($ArrCreatures, $type) {
		    if ($ArrCreatures!="") {
				$i=0;
				foreach($ArrCreatures as $stack) {
					//print "!!!!!!!";
					//print_r($stack);
					$Stacks[$i]['code']=$stack['code'];
					$Stacks[$i]['creature_id']=$stack['id'];
					$Stacks[$i]['initiative']=$stack['initiative'];
		    		$Stacks[$i]['att_rating']=$stack['att_rating'];
		    		$Stacks[$i]['def_rating']=$stack['def_rating'];
		    		$Stacks[$i]['damage']=$stack['damage'];
		    		$Stacks[$i]['pct_fatigue']=$stack['pct_fatigue'];
		    		$Stacks[$i]['pct_death_toll']=$stack['pct_death_toll'];
		    		foreach($stack['abilities'] as $ability) {
		    			$Stacks[$i]['abilities'][]=$ability['code'];
		    		}
		    		//$Stacks[$i]['hit_points']=$stack['hit_points']*$stack['total_number'];
		    		$Stacks[$i]['hit_points']=$stack['hit_points'];
		    		$Stacks[$i]['number_left']=$stack['total_number'];
		    		$Stacks[$i]['number_total']=$stack['total_number'];
		    		$Stacks[$i]['net_power']=$stack['net_power'];	
		    		
		    		//Following 2 values are only used to determine right stack sorting at end of fct.
		    		//It means that these 2 vars are being carried uselessly from then on...not good!
		    		$Stacks[$i]['total_att_rating']=$stack['att_rating']*$stack['total_number']; 
		    		$Stacks[$i]['total_def_rating']=$stack['def_rating']*$stack['total_number'];    		
					
		    		$i++;
				}
	
			} else {	
				$returnMsg = "Missing mageid!";
			}
			
			if ($type=='att')
				$Stacks=$this->array_sort($Stacks, 'total_att_rating', SORT_DESC);
			else if ($type=='def')
				$Stacks=$this->array_sort($Stacks, 'total_def_rating', SORT_DESC);
			
			//print_r($Stacks);
			
			return $Stacks;
        	
        	
        }
        
		function array_sort($array, $on, $order=SORT_ASC, $preserve_keys=0)
		{
		    $new_array = array();
		    $sortable_array = array();
		
		    if (count($array) > 0) {
		        foreach ($array as $k => $v) {
		            if (is_array($v)) {
		                foreach ($v as $k2 => $v2) {
		                    if ($k2 == $on) {
		                        $sortable_array[$k] = $v2;
		                    }
		                }
		            } else {
		                $sortable_array[$k] = $v;
		            }
		        }
		
		        switch ($order) {
		            case SORT_ASC:
		                asort($sortable_array);
		            break;
		            case SORT_DESC:
		                arsort($sortable_array);
		            break;
		        }
		
		        foreach ($sortable_array as $k => $v) {
		            if ($preserve_keys)
		        		$new_array[$k] = $array[$k];
		        	else
		        		$new_array[] = $array[$k];
		        }
		    }
		
		    return $new_array;
		}        
        
        public function getHeroInfos($mageid, $heroid=0) {
		    if ($mageid!="") {
			
				// Database credentials
			   $host = 'localhost'; 
			   $db = 'Archmage';    
			   $uid = 'root';
			   $pwd = 'ben0828';
			 
				//connect to the database server   
			    $link = mysql_connect($host, $uid, $pwd) or die("Could not connect");
			   
			   //select the database
			   mysql_select_db($db) or die("Could not select database");
			   
			   $sql="select * from hero where mage_id = " . $mageid;
			   
			   $rs=mysql_query($sql, $link);
			   
		       while($row=mysql_fetch_assoc($rs)) {
					$hero[$row['id']] = $row;
			   		
					//select hero class infos
					$sql2="select * from hero_class where hero_class.id = " . $row['hero_class_id'];
			   		$rs2=mysql_query($sql2, $link);
			   		$row2=mysql_fetch_assoc($rs2);	
			   		$hero[$row['id']]['class_infos']=$row2;
			   		
			   		
					//select hero class infos
					$sql2="select * from ability where id in (select ability_id from hero_class_has_ability where hero_class_id = " . $row['hero_class_id'] . ") ";
			   		$rs2=mysql_query($sql2, $link);
			   		while($row2=mysql_fetch_assoc($rs2)) {	
			   			$hero[$row['id']]['class_abilities'][$row2['code']]=$row2;
			   		}
			   		
			   			     			
					
   				}
			} else {	
				$returnMsg = "Missing mageid!";
			}
			
			//print "<pre>";
			//print_r($hero);
			
			return $hero;
        	
        }
        
        public function getCreatureInfos($mageid, $creatureid=0) {
		    if ($mageid!="") {
			
				// Database credentials
			   $host = 'localhost'; 
			   $db = 'Archmage';    
			   $uid = 'root';
			   $pwd = 'ben0828';
			 
				//connect to the database server   
			    $link = mysql_connect($host, $uid, $pwd) or die("Could not connect");
			   
			   //select the database
			   mysql_select_db($db) or die("Could not select database");
			   
			   	$sql="select * from creature where id in (select creature_id from mage_has_creature where mage_id = " . $mageid . ")";
			   
			   $rs=mysql_query($sql, $link);
			   
		       while($row=mysql_fetch_assoc($rs)) {
					$creature[$row['id']] = $row;
			   		
					//select creature type
					$sql2="select * from creature_type where id = " . $row['id'];
			   		$rs2=mysql_query($sql2, $link);
			   		$row2=mysql_fetch_assoc($rs2);	
			   		$creature[$row['id']]['type']=$row2['code'];
			   		
					//select creature type
					$sql2="select total_number from mage_has_creature where mage_id = " . $mageid . " and creature_id = " . $row['id'];
			   		$rs2=mysql_query($sql2, $link);
			   		$row2=mysql_fetch_assoc($rs2);	
			   		$creature[$row['id']]['total_number']=$row2['total_number'];
			   					   		
					//select creature abilities
					$sql2="select * from ability where id in (select ability_id from creature_has_ability where creature_id = " . $row['id'] . ") ";
			   		$rs2=mysql_query($sql2, $link);
			   		while($row2=mysql_fetch_assoc($rs2)) {	
			   			$creature[$row['id']]['abilities'][$row2['code']]=$row2;
			   		}
			   		
			   			     			
					
   				}
			} else {	
				$returnMsg = "Missing mageid!";
			}
			
			//print "<pre>";
			//print_r($creature);
			
			return $creature;
        	
        	
        }

        public function countrysearchAction()
        {
        	$query = $this->getRequest()->getParam("query");
               
       		$countryBusinessObject = new App_Countries();    
               
       		echo Zend_Json_Encoder::encode($countryBusinessObject->searchCountries($query));
        }
        
        //This function needs to be re-factored to use the ZF classes..
        //I simply ported it from straight PHP to this ZF controller yet..
        public function createaccountAction()
        {
			$returnMsg="";  	
			
		    $app_username = htmlspecialchars($this->getRequest()->getParam("username"), ENT_QUOTES);
		    $app_password = htmlspecialchars($this->getRequest()->getParam("password"), ENT_QUOTES);  
		    $magic_school_id = $this->getRequest()->getParam("magicschool");
			
		    //$app_username="Raistlin";
			//$app_password="lenoir";
			//$magic_school_id=5;   			
		    //print_r($this->getRequest());
			
		    if ($app_username!=""
					&& $app_password!="" 
							&& is_numeric($magic_school_id)) {
			
				// Database credentials
			   $host = 'localhost'; 
			   $db = 'Archmage';    
			   $uid = 'root';
			   $pwd = 'ben0828';
			 
				//connect to the database server   
			    $link = mysql_connect($host, $uid, $pwd) or die("Could not connect");
			   
			   //select the json database
			   mysql_select_db($db) or die("Could not select database");
			   
			   //Check if username is already taken
			   $sql="select count(id) as id from mage where lower(app_username)=lower('{$uid}')";
			   
			   $rs = mysql_query($sql, $link);
			   
			   $row = mysql_fetch_row($rs);
			   
			   if ($row[0] > 0) {
			   		$returnMsg="Username already exists";
			   } else {
				   //create user in database
				   $sql="insert into mage (magic_school_id, protected_until, app_username, app_password) values ('{$magic_school_id}', date_add(CURRENT_TIMESTAMP(), interval 3 day), '{$app_username}',md5('{$app_password}'));";  
				   
				   mysql_query($sql, $link);
				   	
				   $returnMsg=mysql_insert_id();
				   
				   //Create the user's stats row in DB
				   $sqlStats="insert into mage_stats (mage_id, combats_won, combats_lost) values ('{$returnMsg}', 0, 0);";  				   
				   mysql_query($sqlStats, $link);
			   }
			} else {	
				$returnMsg = "Missing username, password or magic school";
			}
			
			echo $returnMsg; 
        }
        
        public function archmageinfosAction()
        {
        	$a_id = $this->getRequest()->getParam("id");
        	
        	if (!is_numeric($a_id))				
				throw new Exception("Invalid mage Id");

        	$archmage = new App_Archmage();
                	
 			echo Zend_Json_Encoder::encode($archmage->getArchmageInfos($a_id));
        }	
        
        private function authAccepted()
        {
       
                $this->appName = $this->getRequest()->getParam('appName');
                $keys = $this->config->api->toArray();

                $this->clientKey = $keys[$this->appName]['secret'];
                $signature = $this->signArgs($_POST);


                if ($signature == $this->getRequest()->getParam('auth'))
                        return true;
               
                return false;;
        }
        
        private function signArgs($args){
                ksort($args);
                $a = '';
                foreach($args as $k => $v)
                {
                        if ($k == 'auth')
                                continue;

                        $a .= $k . $v;
                }
                return md5($this->clientKey.$a);
        }
}
?>

