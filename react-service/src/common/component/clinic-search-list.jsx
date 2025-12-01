import { Card, Typography, Spin, Row, Col } from "antd";
import ClinicCard from "./clinic-card.jsx";

export default function ClinicSearchList({
	clinics,
	searchText,
	loading,
	hasSearched,
}) {
	if (loading) {
		return (
			<Spin
				size="large"
				style={{ display: "block", margin: "50px auto" }}
			/>
		);
	}

	if (!hasSearched) {
		return null;
	}

	return (
		<div style={{ marginTop: 20 }}>
			{clinics.length > 0 ? (
				<>
					<Typography.Text
						type="secondary"
						style={{ marginBottom: 16, display: "block" }}
					>
						Chúng tôi tìm thấy {clinics.length} kết quả
						{searchText && ` cho "${searchText}"`}
					</Typography.Text>

					<Row gutter={[16, 16]}>
						{clinics.map((clinic, index) => (
							<Col key={index} xs={24} sm={12} md={12} lg={6}>
								<ClinicCard clinic={clinic} loading={loading} />
							</Col>
						))}
					</Row>
				</>
			) : (
				<Card>
					<Typography.Text type="secondary">
						Không tìm thấy thông tin nào!
					</Typography.Text>
				</Card>
			)}
		</div>
	);
}
