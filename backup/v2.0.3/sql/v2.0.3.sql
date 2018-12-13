ALTER TABLE `__PREFIX__goods` 
	CHANGE `prom_type` `prom_type` tinyint(1)   NULL DEFAULT 0 COMMENT '0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠,4预售' after `sales_sum`; 

ALTER TABLE `__PREFIX__order` 
	ADD COLUMN `order_prom_type` tinyint(4)   NULL DEFAULT 0 COMMENT '0默认1抢购2团购3优惠4预售5虚拟' after `pay_time` , 
	CHANGE `order_prom_id` `order_prom_id` smallint(6)   NOT NULL DEFAULT 0 COMMENT '活动id' after `order_prom_type` , 
	CHANGE `order_prom_amount` `order_prom_amount` decimal(10,2)   NOT NULL DEFAULT 0.00 COMMENT '活动优惠金额' after `order_prom_id` , 
	CHANGE `discount` `discount` decimal(10,2)   NOT NULL DEFAULT 0.00 COMMENT '价格调整' after `order_prom_amount` , 
	CHANGE `user_note` `user_note` varchar(255)  COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '用户备注' after `discount` , 
	CHANGE `admin_note` `admin_note` varchar(255)  COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '管理员备注' after `user_note` , 
	CHANGE `parent_sn` `parent_sn` varchar(100)  COLLATE utf8_general_ci NULL COMMENT '父单单号' after `admin_note` , 
	CHANGE `is_distribut` `is_distribut` tinyint(1)   NULL DEFAULT 0 COMMENT '是否已分成0未分成1已分成' after `parent_sn` , 
	ADD COLUMN `paid_money` decimal(10,2)   NULL DEFAULT 0.00 COMMENT '订金' after `is_distribut`; 

ALTER TABLE `__PREFIX__order_goods` 
	CHANGE `prom_type` `prom_type` tinyint(1)   NULL DEFAULT 0 COMMENT '0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠,4预售' after `is_comment`; 