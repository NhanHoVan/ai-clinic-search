import { Row, Col, Input, Button, Space } from "antd";
import SelectField from "./select-field.jsx";

export default function SearchHeader({
	provinces,
	districts,
	selectedProvince,
	selectedDistrict,
	searchText,
	loading,
	handleProvinceChange,
	handleDistrictChange,
	setSearchText,
	handleSearch,
}) {
	return (
		<div style={{ marginBottom: 24 }}>
			<Row gutter={[16, 16]}>
				{/* Province */}
				<Col xs={24} sm={12} md={6}>
					<SelectField
						label="Chọn tỉnh/thành phố"
						value={selectedProvince}
						onChange={handleProvinceChange}
						options={provinces.map((p) => ({
							label: p.name,
							value: p.code,
						}))}
						placeholder="Chọn tỉnh/thành phố"
						disabled={loading}
					/>
				</Col>

				{/* District */}
				<Col xs={24} sm={12} md={6}>
					<SelectField
						label="Chọn xã/phường"
						value={selectedDistrict}
						onChange={handleDistrictChange}
						options={districts.map((d) => ({
							label: d.name,
							value: d.code,
						}))}
						placeholder="Chọn xã/phường"
						disabled={loading || !districts.length}
					/>
				</Col>

				{/* Search box */}
				<Col xs={24} sm={24} md={12}>
					<div className="flex-col">
						<span className="label-input">
							Nhập từ khóa cần tìm
						</span>
						<Space.Compact style={{ width: "100%" }}>
							<Input
								allowClear
								style={{ paddingTop: 0, paddingBottom: 0 }}
								placeholder="Nhập tên phòng khám, bệnh viện..."
								value={searchText}
								size="large"
								onChange={(e) => setSearchText(e.target.value)}
							/>
							<Button
								type="primary"
								size="large"
								loading={loading}
								onClick={() => handleSearch(searchText)}
							>
								Search
							</Button>
						</Space.Compact>
					</div>
				</Col>
			</Row>
		</div>
	);
}
