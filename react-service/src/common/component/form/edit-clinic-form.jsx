import { useState } from "react";
import { Form, Input, Radio, Select } from "antd";

const { TextArea } = Input;

export default function EditClinicForm({
	form,
	onFinish,
	provinces,
	districts,
	selectedProvince,
	selectedDistrict,
	onProvinceChange,
}) {
	const handleProvince = (value) => {
		if (value === selectedProvince) {
			form.setFieldsValue({
				province: value,
				district: selectedDistrict,
			});
			return;
		}

		form.setFieldValue("district", undefined);
		onProvinceChange(value);
	};
	return (
		<Form
			form={form}
			// layout="vertical"
			onFinish={onFinish}
			labelCol={{ flex: "200px" }}
			labelAlign="left"
			labelWrap
			wrapperCol={{ flex: 1 }}
		>
			<Form.Item label="ID" name="id">
				<Input disabled />
			</Form.Item>

			<Form.Item label="Place ID" name="place_id">
				<Input disabled />
			</Form.Item>

			<Form.Item
				label="Tên phòng khám"
				name="name"
				rules={[
					{ required: true, message: "Vui lòng nhập tên phòng khám" },
				]}
			>
				<Input />
			</Form.Item>

			<Form.Item
				label="Địa chỉ"
				name="address"
				rules={[{ required: true, message: "Vui lòng nhập địa chỉ" }]}
			>
				<Input />
			</Form.Item>

			<Form.Item
				label="Tỉnh / Thành phố"
				name="province"
				rules={[
					{ required: true, message: "Vui lòng chọn tỉnh/thành phố" },
				]}
			>
				<Select
					placeholder="Chọn tỉnh / thành phố"
					options={provinces.map((p) => ({
						label: p.name,
						value: p.code,
					}))}
					onChange={handleProvince}
					showSearch
					optionFilterProp="label"
					filterSort={(a, b) =>
						(a?.label ?? "").localeCompare(b?.label ?? "")
					}
				/>
			</Form.Item>

			<Form.Item
				label="Quận / Huyện"
				name="district"
				rules={[
					{ required: true, message: "Vui lòng chọn quận/huyện" },
				]}
			>
				<Select
					placeholder="Chọn quận / huyện"
					disabled={!selectedProvince}
					options={districts.map((d) => ({
						label: d.name,
						value: d.code,
					}))}
					showSearch
					optionFilterProp="label"
					filterSort={(a, b) =>
						(a?.label ?? "").localeCompare(b?.label ?? "")
					}
				/>
			</Form.Item>

			<Form.Item label="Số điện thoại" name="phone">
				<Input />
			</Form.Item>

			<Form.Item
				label="Email"
				name="email"
				rules={[{ type: "email", message: "Email không hợp lệ" }]}
			>
				<Input />
			</Form.Item>

			<Form.Item label="Website" name="website">
				<Input />
			</Form.Item>

			<Form.Item
				label="Mô tả"
				name="description"
				rules={[{ max: 500, message: "Tối đa 500 ký tự" }]}
			>
				<TextArea rows={4} maxLength={500} showCount />
			</Form.Item>

			<Form.Item label="Ảnh đại diện (URL)" name="image">
				<Input />
			</Form.Item>

			<Form.Item
				label="Bạn là"
				name="verifier"
				rules={[
					{
						required: true,
						message: "Vui lòng cho chúng tôi biết bạn là ai?",
					},
				]}
			>
				<Radio.Group>
					<Radio value="owner"> Chủ/người đại diện của cơ sở </Radio>
					<Radio value="other"> Người đóng góp thông tin </Radio>
				</Radio.Group>
			</Form.Item>
		</Form>
	);
}
