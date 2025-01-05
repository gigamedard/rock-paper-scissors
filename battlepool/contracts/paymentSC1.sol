// SPDX-License-Identifier: MIT
pragma solidity ^0.8.23;
import "hardhat/console.sol";

contract PaymentSC1 {
    mapping(address => uint256) public userBalances;
    event DepositReceived(address indexed user, uint256 amount);

    constructor() {
        //console.log("Contract deployed at:", address(this));
    }


    function deposit() external payable {
         console.log("msg.value:", msg.value);
        //require(msg.value > 0, "Deposit must be greater than 0");
        _processDeposit(msg.sender, msg.value);
    }

    receive() external payable {
        require(msg.value > 0, "receive() :amphora:Deposit must be greater than 0");
        console.log("Contract address:", address(this));
        emit DepositReceived(msg.sender, msg.value);
        
    }

    fallback() external payable {
        require(msg.value > 0, "fallback :ambulance:Deposit must be greater than 0");
        emit DepositReceived(msg.sender, msg.value);
    }

    function _processDeposit(address user, uint256 amount) private {
        userBalances[user] += amount;
        emit DepositReceived(user, amount);
    }

    function getUserBalance(address user) external view returns (uint256) {
        return userBalances[user];
    }

    function getContractAddress() external view returns (address) {
        return address(this);
    }
    
}


