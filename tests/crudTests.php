<?php
/**
 * Created by PhpStorm.
 * User: Ravi Patel
 * Date: 09-Apr-18
 * Time: 11:39 AM
 */
require_once dirname(__FILE__) . "/../CRUD.php";

class customer extends CRUD {
    public $name;

    public $email;

    public $phone;
}

class insurance extends CRUD {

    public $policyNumber;

    public $sumAssured;

    public $customerId;

    public $customer;

    public function __construct()
    {
        $this->customer = new customer();
    }

    static $relations = array(
      "customer" => array('id', 'customerId')
    );
}

$customer = new customer();
$customer->name = 'Ravi Patel';
$customer->email = 'ravi@rbsoft.org';
$customer->phone = '9999999999';
$ins = new insurance();
$ins->policyNumber = 'ABCD1234';
$ins->sumAssured = 100000;
$ins->customer = $customer;
$ins->create();
print_r($ins);
echo "<br/>";


$ins = new insurance();
$ins->policyNumber = 'XYZ12345';
$ins->sumAssured = 200000;
$ins->customerId = 11;
$ins->create();
print_r($ins);
echo "<br/>";


$insRead = new insurance();
$cust = new customer();
$cust->name = "Darshan Patel";
$insRead->customer = $cust;
$insRead->read();
print_r($insRead);
echo "<br/>";


$ins = new insurance();
$ins->id = 23;
$ins->policyNumber = "Special";
$ins->customerId = 11;
$ins->customer->name = "Karan Patel";
$ins->update();
print_r($ins);
echo "<br/>";


$insRead2 = new insurance();
$insRead2->id = 23;
$insRead2->read();
print_r($insRead2);
echo "<br/>";


$data = insurance::read_all();
print_r($data);
echo "<br/>";

$sum = insurance::where("customerId", "11")->sum("sumAssured");
echo "Total sumAssured for customer 11 is {$sum}";
echo "<br/>";
