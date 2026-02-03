import { useState, useEffect, useCallback, useMemo } from "react";
import { Form, Input, Select, message, Row, Col, Space, Divider } from "antd";
import { useWordPress } from "@/hooks/use-wordpress";
import { extractLatLngFromGoogleMapUrl } from "@/utils/utils";

const { TextArea } = Input;

export default function EditClinicAdminForm({
	form,
	onFinish,
	provinces,
	specialties,
}) {
	const { apiFetch } = useWordPress();
	const [districts, setDistricts] = useState([]);

	const selectedProvince = Form.useWatch("province", form);

	const fetchDistricts = useCallback(
		async (provinceCode) => {
			if (!provinceCode) {
				setDistricts([]);
				return;
			}

			try {
				const data = await apiFetch("/districts", {
					body: { province_code: provinceCode },
				});
				setDistricts(Array.isArray(data) ? data : []);
			} catch (e) {
				message.error(
					e?.message || "Không thể tải danh sách quận/huyện",
				);
			}
		},
		[apiFetch],
	);

	useEffect(() => {
		fetchDistricts(selectedProvince);
	}, [selectedProvince, fetchDistricts]);

	const handleProvinceChange = (value) => {
		form.setFieldsValue({
			province: value,
			district: undefined,
		});
	};

	const options = useMemo(
		() =>
			(specialties || []).map((s) => ({
				label: s.name,
				value: s.name,
			})),
		[specialties],
	);

	const handleGoogleMapChange = (e) => {
		const value = e.target.value;
		if (!value) return;

		const latLng = extractLatLngFromGoogleMapUrl(value);

		if (!latLng) {
			message.warning("Link Google Map không hợp lệ");
			return;
		}

		form.setFieldsValue({
			lat: latLng.lat,
			lng: latLng.lng,
		});
	};

	return (
		<Form
			form={form}
			onFinish={onFinish}
			labelCol={{ flex: "200px" }}
			labelAlign="left"
			labelWrap
			wrapperCol={{ flex: 1 }}
		>
			<Form.Item label="ID" name="id">
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
				label="Chuyên khoa"
				name="specialties"
				rules={[
					{
						required: true,
						message: "Vui lòng nhập các chuyên khoa phù hợp",
					},
				]}
			>
				<Select
					mode="tags"
					placeholder="Nhập hoặc chọn chuyên khoa"
					options={options}
					allowClear
					showSearch
				/>
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
					onChange={handleProvinceChange}
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

			<Form.Item label="Ảnh hiển thị (URL)" name="image">
				<Input />
			</Form.Item>

			<Form.Item
				label="Google Map URL"
				name="google_map_url"
				validateTrigger="onBlur"
				rules={[
					{
						validator: (_, value) => {
							if (!value) return Promise.resolve();

							return extractLatLngFromGoogleMapUrl(value)
								? Promise.resolve()
								: Promise.reject(
										new Error(
											"Link Google Map không hợp lệ",
										),
									);
						},
					},
				]}
			>
				<Space direction="vertical" className="space-input-form">
					<Input
						placeholder="Dán link Google Map tại đây"
						onBlur={handleGoogleMapChange}
					/>

					<Row gutter={16}>
						<Col span={12}>
							<Form.Item name="lat" noStyle>
								<Input placeholder="lat" disabled />
							</Form.Item>
						</Col>

						<Col span={12}>
							<Form.Item name="lng" noStyle>
								<Input placeholder="lng" disabled />
							</Form.Item>
						</Col>
					</Row>
				</Space>
			</Form.Item>

			<Form.Item
				label="Mô tả"
				name="description"
				rules={[{ max: 500, message: "Tối đa 500 ký tự" }]}
			>
				<TextArea rows={3} maxLength={500} showCount />
			</Form.Item>

			<Divider size="small" style={{ marginBottom: 15, marginTop: 25 }} />

			<Form.Item
				label="Trạng thái xác thực"
				name="verify_status"
				rules={[
					{
						required: true,
						message: "Vui lòng chọn trạng thái xác thực",
					},
				]}
			>
				<Select
					options={[
						{ label: "AI phát hiện", value: "AI_DETECTED" },
						{
							label: "Đã xác thực bởi admin",
							value: "ADMIN_VERIFIED",
						},
						{
							label: "Đã xác thực bởi chủ sở hữu",
							value: "OWNER_VERIFIED",
						},
					]}
				/>
			</Form.Item>

			<Form.Item
				label="Trạng thái hiển thị"
				name="is_active"
				rules={[
					{
						required: true,
						message: "Vui lòng chọn trạng thái hiển thị",
					},
				]}
				style={{ marginBottom: 40 }}
			>
				<Select
					options={[
						{ label: "Hiển thị", value: "ACTIVE" },
						{ label: "Ẩn", value: "HIDDEN" },
					]}
				/>
			</Form.Item>
		</Form>
	);
}
